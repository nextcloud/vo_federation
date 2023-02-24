<?php

namespace OCA\VO_Federation\Tests\Unit\Settings;

use OCA\VO_Federation\Service\ProviderService;

use OCA\VO_Federation\Settings\Admin;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class AdminSettingsTest extends TestCase {
	protected Admin $settings;
	protected $providerService;
	/** @var \PHPUnit\Framework\MockObject\MockObject|IURLGenerator */
	protected $urlGenerator;
	/** @var \PHPUnit\Framework\MockObject\MockObject|IInitialState */
	protected $initialStateService;

	public function setUp(): void {
		$this->providerService = $this->createMock(ProviderService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->initialStateService = $this->createMock(IInitialState::class);

		$this->settings = new Admin($this->providerService, $this->urlGenerator, $this->initialStateService);
	}

	public function testGetForm() {
		$result = $this->settings->getForm();

		$this->assertEquals('adminSettings', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}
}
