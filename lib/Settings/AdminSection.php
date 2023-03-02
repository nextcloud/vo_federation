<?php

declare(strict_types=1);
/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\VO_Federation\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	/** @var IL10N */
	private $l;

	/** @var IURLGenerator */
	private $urlGenerator;

	/**
	 * @param IL10N $l
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(IL10N $l,
								IURLGenerator $urlGenerator) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getID() {
		return 'community-aais';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return $this->l->t('Community AAIs');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority() {
		return 75;
	}

	public function getIcon(): ?string {
		return $this->urlGenerator->imagePath('core', 'categories/integration.svg');
	}
}
