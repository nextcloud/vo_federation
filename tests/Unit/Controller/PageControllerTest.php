<?php

namespace OCA\VO_Federation\Tests\Unit\Controller;

use OCA\VO_Federation\Controller\PageController;

use OCP\AppFramework\Http\TemplateResponse;

use PHPUnit_Framework_TestCase;

class PageControllerTest extends PHPUnit_Framework_TestCase {
	private $controller;
	private $userId = 'john';

	public function setUp() {
		$request = $this->getMockBuilder('OCP\IRequest')->getMock();

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
