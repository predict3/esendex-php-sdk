<?php
/**
 * Copyright (c) 2019, Commify Ltd.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of Commify nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Http
 * @package    Esendex
 * @author     Commify Support <support@esendex.com>
 * @copyright  2019 Commify Ltd.
 * @license    http://opensource.org/licenses/BSD-3-Clause  BSD 3-Clause
 * @link       https://github.com/esendex/esendex-php-sdk
 */
namespace Esendex\Http;

use Esendex\Authentication\IAuthentication;
use Esendex\Model\Api;

class HttpClient implements IHttp
{
    private const CA_BUNDLE = __DIR__ . '/../../ca-bundle.pem';

    private static ?string $userAgent = null;

    private bool $isSecure;

    public function __construct($secure = true)
    {
        $this->isSecure = (bool) $secure;
    }

    public function isSecure($secure = null): bool
    {
        if (is_bool($secure)) {
            $this->isSecure = $secure;
        }
        return $this->isSecure;
    }

    public function get($url, IAuthentication $authentication): string
    {
        return $this->request($url, $authentication, 'GET');
    }

    public function post($url, IAuthentication $authentication, $data): string
    {
        return $this->request($url, $authentication, 'POST', $data);
    }

    private function request(string $url, IAuthentication $authentication, string $method, $data = null): string
    {
        $headers = ["Authorization: {$authentication->getEncodedValue()}"];

        $curl = \curl_init();
        \curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_FAILONERROR => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => false,
            CURLOPT_CAINFO => self::CA_BUNDLE,
            CURLOPT_USERAGENT => self::userAgent(),
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($method === 'POST') {
            \curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            if (strlen((string) $data) == 0) {
                $headers[] = 'Content-Length: 0';
            }
            $headers[] = 'Content-Type: application/xml; charset=utf-8';
        }
        \curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $body = \curl_exec($curl);
        $info = \curl_getinfo($curl);

        if ($info['http_code'] < 200 || $info['http_code'] >= 300) {
            throw $this->getHttpException($info['http_code'], $body, $info);
        }

        return $body;
    }

    private function getHttpException(int $httpCode, string|false $body, array $info): \Exception
    {
        $message = strlen((string) $body) != 0 && $body != $httpCode
            ? $body
            : "The requested URL returned error: {$httpCode}";

        return match ($httpCode) {
            400, 401, 402, 403, 404, 405, 408, 500, 501, 503 => new HttpException($message, $httpCode, $info),
            default => new \Exception("An unexpected error occurred processing the web request", $httpCode),
        };
    }

    private static function userAgent(): string
    {
        if (self::$userAgent === null) {
            $agent = "esendex-php-sdk/" . Api::getVersion()
                . " (" . php_uname("s") . " " . php_uname("v") . "; " . php_uname("m") . ")"
                . " PHP/" . PHP_VERSION . " (" . PHP_OS . ")";
            $curlVersion = \curl_version();
            if (isset($curlVersion["version"])) {
                $agent .= " curl/" . $curlVersion["version"];
            }
            self::$userAgent = $agent;
        }

        return self::$userAgent;
    }
}
