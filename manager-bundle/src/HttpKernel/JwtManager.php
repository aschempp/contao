<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\ManagerBundle\HttpKernel;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Exception\ResponseException;
use Firebase\JWT\JWT;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtManager
{
    public const ATTRIBUTE = '_jwtManager';
    private const JWT_KEY = '_contao_preview';

    /**
     * @var string
     */
    private $secret;

    public function __construct(string $projectDir)
    {
        $filesystem = new Filesystem();
        $secretFile = $projectDir.'/var/jwt_secret';

        if ($filesystem->exists($secretFile)) {
            $this->secret = file_get_contents($secretFile);
        }

        if (!\is_string($this->secret) || \strlen($this->secret) !== 64) {
            $this->secret = bin2hex(random_bytes(32));
            $filesystem->dumpFile($secretFile, $this->secret);
        }
    }

    /**
     * @throws ResponseException
     */
    public function parseRequest(Request $request): ?array
    {
        $request->attributes->set(self::ATTRIBUTE, $this);

        if ($request->cookies->has(self::JWT_KEY)) {
            try {
                return $this->decodeJwt((string) $request->cookies->get(self::JWT_KEY));
            } catch (\Exception $e) {
                // Do nothing
            }
        }

        if ($request->getRequestUri() === '/'.$request->getScriptName().'/contao/login') {
            return null;
        }

        throw new RedirectResponseException('/'.$request->getScriptName().'/contao/login');
    }

    /**
     * Adds JWT cookie to the given response.
     */
    public function addResponseCookie(Response $response, array $payload = []): void
    {
        if ($this->hasCookie($response)) {
            return;
        }

        $payload['iat'] = time();
        $payload['exp'] = strtotime('+30 minutes');

        $cookie = new Cookie(
            self::JWT_KEY,
            JWT::encode($payload, $this->secret, 'HS256'),
            0,
            '/'
        );

        $response->headers->setCookie($cookie);
    }

    /**
     * Clears the JWT cookie in the response.
     */
    public function clearResponseCookie(Response $response): Response
    {
        $response->headers->clearCookie(self::JWT_KEY);

        return $response;
    }

    /**
     * Returns whether the response has a cookie with that name.
     */
    private function hasCookie(Response $response): bool
    {
        /** @var Cookie[] $cookies */
        $cookies = $response->headers->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie->getName() === self::JWT_KEY) {
                return true;
            }
        }

        return false;
    }

    private function decodeJwt(string $data): array
    {
        $jwt = JWT::decode(
            $data,
            $this->secret,
            ['HS256']
        );

        // recursively decode the data as array instead of object
        return json_decode(json_encode($jwt), true);
    }
}
