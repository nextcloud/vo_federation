<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\VO_Federation\Avatar;

use OC\NotSquareException;
use OCP\Color;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IAvatar;
use OCP\IConfig;
use OCP\IImage;
use OCP\IL10N;
use Psr\Log\LoggerInterface;


/**
 * This class represents a registered user's avatar.
 */
class NamedAvatar implements IAvatar{
	private IConfig $config;
	private ISimpleFolder $folder;
	private IL10N $l;
	private string $displayName;
	private LoggerInterface $logger;

	/**
	 * UserAvatar constructor.
	 *
	 * @param IConfig $config The configuration
	 * @param ISimpleFolder $folder The avatar files folder
	 * @param IL10N $l The localization helper
	 * @param User $user The user this class manages the avatar for
	 * @param LoggerInterface $logger The logger
	 */
	public function __construct(
		ISimpleFolder $folder,
		IL10N $l,
		string $displayName,
		LoggerInterface $logger,
		IConfig $config) {
		$this->folder = $folder;
		$this->l = $l;
		$this->displayName = $displayName;
		$this->logger = $logger;
		$this->config = $config;
	}

	/**
	 * @inheritdoc
	 */
	public function get(int $size = 64, bool $darkTheme = false) {
		try {
			$file = $this->getFile($size, $darkTheme);
		} catch (NotFoundException $e) {
			return false;
		}

		$avatar = new \OCP\Image();
		$avatar->loadFromData($file->getContent());
		return $avatar;
	}

	public function avatarBackgroundColor(string $hash): Color {
		return new Color(255, 255, 255);
	}

	/**
	 * Check if an avatar exists for the user
	 */
	public function exists(): bool {
		return $this->folder->fileExists('avatar.jpg') || $this->folder->fileExists('avatar.png');
	}

	/**
	 * Sets the users avatar.
	 *
	 * @param IImage|resource|string $data An image object, imagedata or path to set a new avatar
	 * @throws \Exception if the provided file is not a jpg or png image
	 * @throws \Exception if the provided image is not valid
	 * @throws NotSquareException if the image is not square
	 * @return void
	 */
	public function set($data): void {
		$img = $this->getAvatarImage($data);
		$data = $img->data();

		$this->validateAvatar($img);

		$this->remove(true);
		$type = $this->getAvatarImageType($img);
		$file = $this->folder->newFile('avatar.' . $type);
		$file->putContent($data);
	}

	/**
	 * Returns an image from several sources.
	 *
	 * @param IImage|resource|string|\GdImage $data An image object, imagedata or path to the avatar
	 * @return IImage
	 */
	private function getAvatarImage($data): IImage {
		if ($data instanceof IImage) {
			return $data;
		}

		$img = new \OCP\Image();
		if (
			(is_resource($data) && get_resource_type($data) === 'gd') ||
			(is_object($data) && get_class($data) === \GdImage::class)
			) {
			$img->setResource($data);
		} elseif (is_resource($data)) {
			$img->loadFromFileHandle($data);
		} else {
			try {
				// detect if it is a path or maybe the images as string
				$result = @realpath($data);
				if ($result === false || $result === null) {
					$img->loadFromData($data);
				} else {
					$img->loadFromFile($data);
				}
			} catch (\Error $e) {
				$img->loadFromData($data);
			}
		}

		return $img;
	}

	/**
	 * Returns the avatar image type.
	 */
	private function getAvatarImageType(IImage $avatar): string {
		$type = substr($avatar->mimeType(), -3);
		if ($type === 'peg') {
			$type = 'jpg';
		}
		return $type;
	}

	/**
	 * Validates an avatar image:
	 * - must be "png" or "jpg"
	 * - must be "valid"
	 * - must be in square format
	 *
	 * @param IImage $avatar The avatar to validate
	 * @throws \Exception if the provided file is not a jpg or png image
	 * @throws \Exception if the provided image is not valid
	 * @throws \Exception if the image is not square
	 */
	private function validateAvatar(IImage $avatar): void {
		$type = $this->getAvatarImageType($avatar);

		if ($type !== 'jpg' && $type !== 'png') {
			throw new \Exception($this->l->t('Unknown filetype'));
		}

		if (!$avatar->valid()) {
			throw new \Exception($this->l->t('Invalid image'));
		}

		if (!($avatar->height() === $avatar->width())) {
			throw new \Exception($this->l->t('Avatar image is not square'));
		}
	}

	/**
	 * Removes the users avatar.
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function remove(bool $silent = false): void {
		$avatars = $this->folder->getDirectoryListing();

		foreach ($avatars as $avatar) {
			$avatar->delete();
		}
	}

	/**
	 * Get the extension of the avatar. If there is no avatar throw Exception
	 *
	 * @throws NotFoundException
	 */
	private function getExtension(): string {
		if ($this->folder->fileExists('avatar.jpg')) {
			return 'jpg';
		} elseif ($this->folder->fileExists('avatar.png')) {
			return 'png';
		}
		throw new NotFoundException;
	}

	/**
	 * Returns the avatar for an user.
	 *
	 * If there is no avatar file yet, one is generated.
	 *
	 * @param int $size
	 * @return ISimpleFile
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function getFile(int $size, bool $darkTheme = false): ISimpleFile {
		$ext = $this->getExtension();

		if ($size === -1) {
			$path = 'avatar.' . $ext;
		} else {
			$path = 'avatar.' . $size . '.' . $ext;
		}

		try {
			$file = $this->folder->getFile($path);
		} catch (NotFoundException $e) {
			if ($size <= 0) {
				throw new NotFoundException;
			}
			$avatar = new \OCP\Image();
			$file = $this->folder->getFile('avatar.' . $ext);
			$avatar->loadFromData($file->getContent());
			$avatar->resize($size);
			$data = $avatar->data();

			try {
				$file = $this->folder->newFile($path);
				$file->putContent($data);
			} catch (NotPermittedException $e) {
				$this->logger->error('Failed to save avatar for provider ' . $this->displayName);
				throw new NotFoundException();
			}
		}

		return $file;
	}

	/**
	 * Returns the user display name.
	 */
	public function getDisplayName(): string {
		return $this->displayName;
	}

	/**
	 * Handles user changes.
	 *
	 * @param string $feature The changed feature
	 * @param mixed $oldValue The previous value
	 * @param mixed $newValue The new value
	 * @throws NotPermittedException
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function userChanged(string $feature, $oldValue, $newValue): void {
		// we skip this
	}

	/**
	 * Check if the avatar of a user is a custom uploaded one
	 */
	public function isCustomAvatar(): bool {
		return true;
	}
}
