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
 * @category   Service
 * @package    Esendex
 * @author     Commify Support <support@esendex.com>
 * @copyright  2019 Commify Ltd.
 * @license    http://opensource.org/licenses/BSD-3-Clause  BSD 3-Clause
 * @link       https://github.com/esendex/esendex-php-sdk
 */
namespace Esendex;

use Esendex\Authentication\IAuthentication;
use Esendex\Exceptions\EsendexException;
use Esendex\Http\HttpClient;
use Esendex\Http\IHttp;
use Esendex\Http\UriBuilder;
use Esendex\Model\DispatchMessage;
use Esendex\Model\ResultItem;
use Esendex\Parser\DispatchXmlParser;

class DispatchService
{
    const DISPATCH_SERVICE = "messagedispatcher";
    const DISPATCH_SERVICE_VERSION = "v1.0";
    const ACCOUNTS_SERVICE = "accounts";
    const ACCOUNTS_SERVICE_VERSION = "v1.0";

    private IAuthentication $authentication;
    private IHttp $httpClient;
    private DispatchXmlParser $parser;

    public function __construct(
        IAuthentication $authentication,
        ?IHttp $httpClient = null,
        ?DispatchXmlParser $parser = null
    ) {
        $this->authentication = $authentication;
        $this->httpClient = $httpClient ?? new HttpClient(true);
        $this->parser = $parser ?? new DispatchXmlParser($authentication->accountReference());
    }

    public function send(DispatchMessage $message): ResultItem
    {
        return $this->dispatch($this->parser->encode($message))[0];
    }

    public function sendMultiple(array $messages): array
    {
        return $this->dispatch($this->parser->encodeMultiple($messages));
    }

    /**
     * Remaining credits of the account, or 0 when they cannot be read.
     */
    public function getCredits(): int
    {
        try {
            $uri = UriBuilder::serviceUri(self::ACCOUNTS_SERVICE_VERSION, self::ACCOUNTS_SERVICE, null, $this->httpClient->isSecure());
            $accounts = new \SimpleXMLElement($this->httpClient->get($uri, $this->authentication));

            foreach ($accounts->account as $account) {
                if (strcasecmp((string) $account->reference, $this->authentication->accountReference()) === 0) {
                    return intval($account->messagesremaining, 10);
                }
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function dispatch(string $xml): array
    {
        $uri = UriBuilder::serviceUri(self::DISPATCH_SERVICE_VERSION, self::DISPATCH_SERVICE, null, $this->httpClient->isSecure());
        $response = $this->httpClient->post($uri, $this->authentication, $xml);

        $results = $this->parser->parse($response);
        if (count($results) === 0) {
            throw new EsendexException("Error parsing the dispatch result", null, ['data_returned' => $response]);
        }

        return $results;
    }
}
