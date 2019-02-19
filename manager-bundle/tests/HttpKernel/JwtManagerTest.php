<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\Tests\HttpKernel;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\ManagerBundle\HttpKernel\JwtManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtManagerTest extends TestCase
{
    /**
     * @var JwtManager
     */
    private $jwtManager;

    public function setUp()
    {
        $this->jwtManager = new JwtManager(sys_get_temp_dir());
    }

    public function tearDown()
    {
        unlink($this->jwtManager->getSecretFile());
    }

    public function testCreatesSecret()
    {
        $this->assertFileExists($this->jwtManager->getSecretFile());
    }

    public function testParseRequestThrowsException()
    {
        $this->expectException(RedirectResponseException::class);
        $request = Request::create('/');
        $this->jwtManager->parseRequest($request);
    }

    public function testParseRequestAndAddResponseCookie()
    {
        $response = new Response();
        $this->jwtManager->addResponseCookie($response);
        $request = Request::create('/');
        $request->cookies->set(JwtManager::COOKIE_NAME, $this->getCookieValueFromResponse($response));

        $result = $this->jwtManager->parseRequest($request);

        $this->assertArrayHasKey('iat', $result);
        $this->assertArrayHasKey('exp', $result);

        $response = new Response();
        $this->jwtManager->addResponseCookie($response, ['foobar' => 'whatever']);
        $request = Request::create('/');
        $request->cookies->set(JwtManager::COOKIE_NAME, $this->getCookieValueFromResponse($response));

        $result = $this->jwtManager->parseRequest($request);

        $this->assertArrayHasKey('iat', $result);
        $this->assertArrayHasKey('exp', $result);
        $this->assertArrayHasKey('foobar', $result);
        $this->assertSAme('whatever', $result['foobar']);
    }

    public function testClearResponseCookie()
    {
        $response = new Response();
        $this->jwtManager->addResponseCookie($response, ['foobar' => 'whatever']);

        $this->assertNotEmpty($this->getCookieValueFromResponse($response));

        $this->jwtManager->clearResponseCookie($response);

        $this->assertEmpty($this->getCookieValueFromResponse($response));
    }

    private function getCookieValueFromResponse(Response $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (JwtManager::COOKIE_NAME === $cookie->getName()) {
                return $cookie->getValue();
            }
        }

        return null;
    }
}
