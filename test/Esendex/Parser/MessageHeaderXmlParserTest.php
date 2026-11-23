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

use Esendex\Model\Message;

class MessageHeaderXmlParserTest extends \PHPUnit\Framework\TestCase
{
    const OUTBOUND_RESPONSE_XML = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<messageheader id="a22702be-881e-43d9-9790-7646a95335f6"
               uri="https://api.esendex.com/v1.0/messageheaders/a22702be-881e-43d9-9790-7646a95335f6"
               xmlns="http://api.esendex.com/ns/">
    <batch id="e84ccbdc-732f-1485-0b03-0aa56519e001" uri="https://api.esendex.com/v1.0/messagebatches/e84ccbdc-732f-1485-0b03-0aa56519e001" />
    <reference>EX123456</reference>
    <status>Delivered</status>
    <deliveredat>2013-03-06T13:20:00Z</deliveredat>
    <sentat>2013-03-06T13:19:20.177Z</sentat>
    <laststatusat>2013-03-06T13:20:00Z</laststatusat>
    <submittedat>2013-03-06T13:18:25.437Z</submittedat>
    <type>SMS</type>
    <to>
        <phonenumber>447123456789</phonenumber>
    </to>
    <from>
        <phonenumber>447987654321</phonenumber>
    </from>
    <summary>Every message matters</summary>
    <body id="a22702be-881e-43d9-9790-7646a95335f6"
          uri="https://api.esendex.com/v1.0/messageheaders/a22702be-881e-43d9-9790-7646a95335f6/body" />
    <direction>Outbound</direction>
    <parts>1</parts>
    <username>support@esendex.com</username>
</messageheader>
XML;

    #[\PHPUnit\Framework\Attributes\Test]
    function parseOutboundMessage()
    {
        $parser = new MessageHeaderXmlParser();

        $result = $parser->parse(self::OUTBOUND_RESPONSE_XML);

        $this->assertInstanceOf("\\Esendex\\Model\\SentMessage", $result);

        $this->assertEquals("a22702be-881e-43d9-9790-7646a95335f6", $result->id());
        $this->assertEquals("447987654321", $result->originator());
        $this->assertEquals("447123456789", $result->recipient());
        $this->assertEquals("Delivered", $result->status());
        $this->assertEquals(Message::SmsType, $result->type());

        $this->assertEquals(
            \DateTime::createFromFormat(\DateTime::ISO8601, "2013-03-06T13:20:00Z"),
            $result->deliveredAt()
        );

        $this->assertEquals("e84ccbdc-732f-1485-0b03-0aa56519e001", $result->batchId());
    }

    const FAILED_OUTBOUND_RESPONSE_XML = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<messageheader id="a22702be-881e-43d9-9790-7646a95335f6"
               uri="https://api.esendex.com/v1.0/messageheaders/a22702be-881e-43d9-9790-7646a95335f6"
               xmlns="http://api.esendex.com/ns/">
    <reference>EX123456</reference>
    <status>Delivered</status>
    <deliveredat>2013-03-06T13:20:00Z</deliveredat>
    <sentat>2013-03-06T13:19:20.177Z</sentat>
    <laststatusat>2013-03-06T13:20:00Z</laststatusat>
    <submittedat>2013-03-06T13:18:25.437Z</submittedat>
    <batch id="e84ccbdc-732f-1485-0b03-0aa56519e001" uri="https://api.esendex.com/v1.0/messagebatches/e84ccbdc-732f-1485-0b03-0aa56519e001" />
    <type>SMS</type>
    <to>
        <phonenumber>447123456789</phonenumber>
    </to>
    <from>
        <phonenumber>447987654321</phonenumber>
    </from>
    <summary>Every message matters</summary>
    <body id="a22702be-881e-43d9-9790-7646a95335f6"
          uri="https://api.esendex.com/v1.0/messageheaders/a22702be-881e-43d9-9790-7646a95335f6/body" />
    <direction>Outbound</direction>
    <parts>1</parts>
    <username>support@esendex.com</username>
    <failurereason>
        <code>142</code>
        <description>Message broken</description>
        <permanentfailure>true</permanentfailure>
    </failurereason>
</messageheader>
XML;

    #[\PHPUnit\Framework\Attributes\Test]
    function parseFailedOutboundMessage()
    {
        $parser = new MessageHeaderXmlParser();

        $result = $parser->parse(self::FAILED_OUTBOUND_RESPONSE_XML);

        $this->assertInstanceOf("\\Esendex\\Model\\SentMessage", $result);

        $this->assertEquals("a22702be-881e-43d9-9790-7646a95335f6", $result->id());
        $this->assertEquals("447987654321", $result->originator());
        $this->assertEquals("447123456789", $result->recipient());
        $this->assertEquals("Delivered", $result->status());
        $this->assertEquals(Message::SmsType, $result->type());

        $this->assertEquals(
            \DateTime::createFromFormat(\DateTime::ISO8601, "2013-03-06T13:20:00Z"),
            $result->deliveredAt()
        );
        $this->assertEquals(142, $result->failureReason()->code());
        $this->assertEquals("Message broken", $result->failureReason()->description());
        $this->assertEquals(true, $result->failureReason()->permanentFailure());
        $this->assertEquals("e84ccbdc-732f-1485-0b03-0aa56519e001", $result->batchId());
    }
}
