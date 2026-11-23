<?php
/**
 * Copyright (c) 2016, Commify Ltd.
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
 * @copyright  2016 Commify Ltd.
 * @license    http://opensource.org/licenses/BSD-3-Clause  BSD 3-Clause
 * @link       https://github.com/esendex/esendex-php-sdk
 */
namespace Esendex;

use Esendex\Authentication\IAuthentication;
use Esendex\Http\HttpClient;
use Esendex\Http\IHttp;
use Esendex\Http\UriBuilder;
use Esendex\Model\OptOut;
use Esendex\Model\OptOutsPage;
use Esendex\Parser\OptOutXmlParser;

class OptOutsService
{
    const SERVICE = "optouts";
    const SERVICE_VERSION = "v1.0";

    private IAuthentication $authentication;
    private IHttp $httpClient;
    private OptOutXmlParser $parser;

    public function __construct(
        IAuthentication $authentication,
        ?IHttp $httpClient = null,
        ?OptOutXmlParser $parser = null
    ) {
        $this->authentication = $authentication;
        $this->httpClient = $httpClient ?? new HttpClient(true);
        $this->parser = $parser ?? new OptOutXmlParser();
    }

    /**
     * Registers a phone number as opted out: the account will no longer send it SMS.
     */
    public function add($accountReference, $mobileNumber): OptOut
    {
        $uri = UriBuilder::serviceUri(self::SERVICE_VERSION, self::SERVICE, null, $this->httpClient->isSecure());
        $request = $this->parser->encodePostRequest($accountReference, $mobileNumber);

        return $this->parser->parsePostResponse($this->httpClient->post($uri, $this->authentication, $request));
    }

    /**
     * Opt-outs of the authenticated account, page by page (first page is 1, 15 opt-outs per page by default).
     */
    public function getMyAccountReference($pageNumber = null, $pageSize = null): OptOutsPage
    {
        if ($pageNumber == null) {
            $pageNumber = 1;
        }
        if ($pageSize == null) {
            $pageSize = 15;
        }

        $uri = UriBuilder::serviceUri(self::SERVICE_VERSION, self::SERVICE, null, $this->httpClient->isSecure())
            . '?' . UriBuilder::buildQuery([
                'startIndex' => ($pageNumber - 1) * $pageSize,
                'count' => $pageSize,
                'accountreference' => $this->authentication->accountReference(),
            ]);

        return $this->parser->parseMultipleResult($this->httpClient->get($uri, $this->authentication));
    }
}
