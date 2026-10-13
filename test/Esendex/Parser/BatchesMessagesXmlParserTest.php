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
 * @category   Testing
 * @package    Esendex
 * @author     Commify Support <support@esendex.com>
 * @copyright  2019 Commify Ltd.
 * @license    http://opensource.org/licenses/BSD-3-Clause  BSD 3-Clause
 * @link       https://github.com/esendex/esendex-php-sdk
 */
namespace Esendex\Parser;

class BatchesMessagesXmlParserTest extends \PHPUnit\Framework\TestCase
{
    private $parser;
    private $errors;

    function setUp(): void
    {
        $this->parser = new BatchesMessagesXmlParser();
        $this->errors = array();
        set_error_handler(function ($errno, $errstr) {
            $this->errors[] = $errstr;
            return true;
        });
    }

    function tearDown(): void
    {
        restore_error_handler();
    }

    const BATCH_MESSAGES_RESPONSE_XML = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<messageheaders startindex="0" count="2" totalcount="2"
                xmlns="http://api.esendex.com/ns/">
  <messageheader id="d6258601-3442-484f-b2ac-300088a8a4d4">
    <status>Delivered</status>
    <to>
      <phonenumber>447123456789</phonenumber>
    </to>
  </messageheader>
  <messageheader id="3e73eb78-65cd-42b4-a0b7-5b8a29603ed1">
    <status>Sent</status>
    <to>
      <phonenumber>447987654321</phonenumber>
    </to>
  </messageheader>
</messageheaders>
XML;

    const BATCH_MESSAGES_WITHOUT_ID_RESPONSE_XML = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<messageheaders startindex="0" count="1" totalcount="1"
                xmlns="http://api.esendex.com/ns/">
  <messageheader>
    <status>Delivered</status>
    <to>
      <phonenumber>447123456789</phonenumber>
    </to>
  </messageheader>
</messageheaders>
XML;

    #[\PHPUnit\Framework\Attributes\Test]
    function parse(): void
    {
        $result = $this->parser->parse(self::BATCH_MESSAGES_RESPONSE_XML);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(\Esendex\Model\BatchesMessage::class, $result[0]);
        $this->assertSame("d6258601-3442-484f-b2ac-300088a8a4d4", $result[0]->id());
        $this->assertSame("Delivered", $result[0]->status());
        $this->assertSame("447123456789", $result[0]->phonenumber());
        $this->assertSame("3e73eb78-65cd-42b4-a0b7-5b8a29603ed1", $result[1]->id());
        $this->assertSame("Sent", $result[1]->status());
        $this->assertSame("447987654321", $result[1]->phonenumber());
        $this->assertSame(array(), $this->errors);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    function parseWithoutId(): void
    {
        $result = $this->parser->parse(self::BATCH_MESSAGES_WITHOUT_ID_RESPONSE_XML);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]->id());
        $this->assertSame("Delivered", $result[0]->status());
        $this->assertSame("447123456789", $result[0]->phonenumber());
        $this->assertSame(array(), $this->errors);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    function propertiesArePubliclyAccessible(): void
    {
        $result = $this->parser->parse(self::BATCH_MESSAGES_RESPONSE_XML);

        $this->assertSame("d6258601-3442-484f-b2ac-300088a8a4d4", $result[0]->id);
        $this->assertSame("Delivered", $result[0]->status);
        $this->assertSame("447123456789", $result[0]->phonenumber);
        $this->assertSame(
            '{"phonenumber":"447123456789","status":"Delivered","id":"d6258601-3442-484f-b2ac-300088a8a4d4"}',
            json_encode($result[0])
        );
        $this->assertSame(array(), $this->errors);
    }
}
