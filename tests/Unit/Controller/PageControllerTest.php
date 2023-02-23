<?php

namespace OCA\VO_Federation\Tests\Unit\Controller;

use OCA\VO_Federation\Controller\PageController;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class PageControllerTest extends TestCase {
	private $controller;
	private $userId = 'john';

	public function setUp(): void {
		$request = $this->createMock(IRequest::class);

		$this->controller = new PageController(
			'vo_federation', $request, $this->userId
		);
	}

	public function testIndex() {
		$result = $this->controller->index();

		$this->assertEquals('index', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}
}
