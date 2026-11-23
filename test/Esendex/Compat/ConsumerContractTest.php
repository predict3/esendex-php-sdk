<?php

namespace Esendex\Compat;

use Esendex\Authentication\LoginAuthentication;
use Esendex\DispatchService;
use Esendex\Exceptions\ArgumentException;
use Esendex\Exceptions\EsendexException;
use Esendex\Http\HttpClient;
use Esendex\Http\IHttp;
use Esendex\Http\UnauthorisedException;
use Esendex\MessageBatchesService;
use Esendex\MessageHeaderService;
use Esendex\Model\DispatchMessage;
use Esendex\Model\Message;
use Esendex\Model\MessageBody;
use Esendex\Model\OptOut;
use Esendex\OptOutsService;
use Esendex\SentMessagesService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Reproduces the calls made by Predict projects, with the real services and parsers.
 * If one of these tests breaks, a consumer project breaks.
 *
 * Every HTTP call goes through a fake IHttp: nothing can be sent for real.
 */
class ConsumerContractTest extends TestCase
{
    private const NS = 'xmlns="http://api.esendex.com/ns/"';

    private const MESSAGE_HEADER = '<messageheader id="%s"><status>%s</status><type>SMS</type>'
        . '<deliveredat>2026-03-01T10:00:04Z</deliveredat><to><phonenumber>33600000001</phonenumber></to>'
        . '<batch id="batch-1" /></messageheader>';

    private LoginAuthentication $authentication;

    protected function setUp(): void
    {
        $this->authentication = new LoginAuthentication('EX0000000', 'fake-login', 'fake-password');
    }

    /**
     * Fake IHttp answering $response to $method, and capturing the called URL and the sent body.
     */
    private function fakeHttp(string $method, string $response, ?string &$url = null, ?string &$body = null): IHttp
    {
        $http = $this->createStub(IHttp::class);
        $http->method('isSecure')->willReturn(true);
        $http->method($method)->willReturnCallback(
            function ($calledUrl, $auth, $data = null) use (&$url, &$body, $response) {
                $url = $calledUrl;
                $body = $data;
                return $response;
            }
        );

        return $http;
    }

    #[Test]
    public function sendAsPhpLib(): void
    {
        $response = '<messageheaders batchid="batch-1" ' . self::NS . '><messageheader id="msg-1" uri="u" /></messageheaders>';
        $service = new DispatchService($this->authentication, $this->fakeHttp('post', $response, $url, $body));

        // php-lib sends validity as the string '0' and several recipients joined by a comma
        $result = $service->send(
            new DispatchMessage('Predict', '33600000001,33600000002', 'Alerte', Message::SmsType, '0', 'fr-FR', MessageBody::CharsetGSM)
        );

        $this->assertSame('msg-1', $result->id());
        $this->assertSame('batch-1', $result->batchid());
        $this->assertSame('https://api.esendex.com/v1.0/messagedispatcher', $url);

        $xml = simplexml_load_string($body);
        $this->assertSame('EX0000000', (string) $xml->accountreference);
        $this->assertSame('GSM', (string) $xml->characterset);
        $this->assertSame('33600000001,33600000002', (string) $xml->message->to);
        $this->assertSame('fr-FR', (string) $xml->message->lang);
        $this->assertFalse(isset($xml->message->validity));
    }

    #[Test]
    public function sendMultipleAsPhpLib(): void
    {
        $response = '<messageheaders batchid="batch-1" ' . self::NS . '>'
            . '<messageheader id="msg-1" uri="u" /><messageheader id="msg-2" uri="u" /></messageheaders>';
        $service = new DispatchService($this->authentication, $this->fakeHttp('post', $response, $url, $body));

        $results = $service->sendMultiple([
            new DispatchMessage('Predict', '33600000001', 'txt', Message::SmsType, 0, 'fr-FR', MessageBody::CharsetGSM),
            new DispatchMessage('Predict', '33600000002', 'txt', Message::SmsType, 0, 'fr-FR', MessageBody::CharsetGSM),
        ]);

        $this->assertSame('msg-2', $results[1]->id());
        $this->assertSame('batch-1', $results[1]->batchid());
        $this->assertCount(2, simplexml_load_string($body)->message);
    }

    #[Test]
    public function invalidMessageIsArgumentException(): void
    {
        // php-lib classifies an ArgumentException as "not attempted"
        $service = new DispatchService($this->authentication, $this->fakeHttp('post', ''));

        $this->expectException(ArgumentException::class);
        $service->send(new DispatchMessage('Predict', '', 'txt', Message::SmsType));
    }

    #[Test]
    public function httpErrorKeepsStatusCode(): void
    {
        // php-lib classifies failures on getCode(): 4xx except 408 => rejected, anything else => unknown
        $toException = new \ReflectionMethod(HttpClient::class, 'getHttpException');

        $badRequest = $toException->invoke(new HttpClient(), ['statuscode' => 400, 'data' => 'Bad request']);
        $this->assertInstanceOf(EsendexException::class, $badRequest);
        $this->assertSame(400, $badRequest->getCode());

        $unknown = $toException->invoke(new HttpClient(), ['statuscode' => 418, 'data' => '']);
        $this->assertSame(418, $unknown->getCode());
    }

    #[Test]
    public function getCreditsAsHealthChecks(): void
    {
        $accounts = '<accounts ' . self::NS . '>'
            . '<account><reference>EX9999999</reference><messagesremaining>5</messagesremaining></account>'
            . '<account><reference>ex0000000</reference><messagesremaining>1234</messagesremaining></account></accounts>';

        $service = new DispatchService($this->authentication, $this->fakeHttp('get', $accounts));
        $this->assertSame(1234, $service->getCredits());

        $otherAccount = new LoginAuthentication('EX1111111', 'fake', 'fake');
        $this->assertSame(0, (new DispatchService($otherAccount, $this->fakeHttp('get', $accounts)))->getCredits());

        $failing = $this->createStub(IHttp::class);
        $failing->method('get')->willThrowException(new UnauthorisedException('no', 401));
        $this->assertSame(0, (new DispatchService($this->authentication, $failing))->getCredits());
    }

    #[Test]
    public function optOutsPageAsUnsubscribeBundle(): void
    {
        $response = '<optouts startindex="25" totalcount="52" ' . self::NS . '>'
            . '<optout id="o-1"><receivedat>2026-01-02T10:11:12Z</receivedat><from><phonenumber>33600000001</phonenumber></from></optout>'
            . '<optout id="o-2"><receivedat>2026-01-03T10:11:12Z</receivedat></optout></optouts>';
        $service = new OptOutsService($this->authentication, $this->fakeHttp('get', $response, $url));

        $page = $service->getMyAccountReference(2, 25);

        $this->assertSame('https://api.esendex.com/v1.0/optouts?startIndex=25&count=25&accountreference=EX0000000', $url);
        $this->assertSame(52, $page->totalCount());
        $this->assertSame('33600000001', $page->optOuts()[0]->from()->phoneNumber());
        $this->assertSame('2026-01-02 10:11:12', $page->optOuts()[0]->receivedAt()->format('Y-m-d H:i:s'));
        // Without <from>, consumers still get a FromAddress (they call from()->phoneNumber() without null check)
        $this->assertNull($page->optOuts()[1]->from()->phoneNumber());
    }

    #[Test]
    public function optOutBuiltByHandAsUnsubscribeBundleTests(): void
    {
        // unsubscribe-bundle tests build opt-outs with a stdClass as "from"
        $from = new \stdClass();
        $from->phonenumber = '+33612345678';
        $optOut = new OptOut();
        $optOut->from($from);

        $this->assertSame('+33612345678', $optOut->from()->phoneNumber());
    }

    #[Test]
    public function addOptOutAsClientCommands(): void
    {
        $response = '<response ' . self::NS . '><optout id="o-9"><receivedat>2026-02-01T08:00:00Z</receivedat>'
            . '<from><phonenumber>33600000009</phonenumber></from></optout></response>';
        $service = new OptOutsService($this->authentication, $this->fakeHttp('post', $response, $url, $body));

        $optOut = $service->add('EX0000000', '33600000009');

        $this->assertSame('https://api.esendex.com/v1.0/optouts', $url);
        $this->assertSame('33600000009', (string) simplexml_load_string($body)->from->phonenumber);
        $this->assertSame('o-9', $optOut->id());
    }

    #[Test]
    public function messageHeaderAsPhpLib(): void
    {
        $xml = str_replace('<messageheader ', '<messageheader ' . self::NS . ' ', sprintf(self::MESSAGE_HEADER, 'msg-1', 'Delivered'));
        $service = new MessageHeaderService($this->authentication, $this->fakeHttp('get', $xml, $url));

        $message = $service->message('msg-1');

        $this->assertSame('https://api.esendex.com/v1.0/messageheaders/msg-1', $url);
        $this->assertSame('batch-1', $message->batchId());
        $this->assertSame('Delivered', $message->status());
        $this->assertSame('33600000001', $message->recipient());
        $this->assertSame('2026-03-01 10:00:04', $message->deliveredAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function sentMessagesAsReportingCommand(): void
    {
        $xml = '<messageheaders totalcount="230" ' . self::NS . '>'
            . sprintf(self::MESSAGE_HEADER, 'msg-1', 'Delivered') . sprintf(self::MESSAGE_HEADER, 'msg-2', 'Failed')
            . '</messageheaders>';
        $service = new SentMessagesService($this->authentication, $this->fakeHttp('get', $xml, $url));

        // Same options as php-lib EsendexSender::getMessagesSentInInterval()
        $page = $service->loadMessages(['start' => '2026-03-01T00:00:00+00:00', 'startIndex' => 100, 'count' => 100]);

        $this->assertSame(
            'https://api.esendex.com/v1.0/messageheaders?start=2026-03-01T00%3A00%3A00%2B00%3A00&startIndex=100&count=100&accountreference=EX0000000',
            $url
        );
        $this->assertSame(230, $page->totalCount());

        $statuses = [];
        while ($page->valid()) {
            $statuses[$page->current()->id()] = $page->current()->status();
            $page->next();
        }
        $this->assertSame(['msg-1' => 'Delivered', 'msg-2' => 'Failed'], $statuses);
    }

    #[Test]
    public function batchMessagesAsPhpLib(): void
    {
        $xml = '<messageheaders ' . self::NS . '>' . sprintf(self::MESSAGE_HEADER, 'msg-1', 'Delivered') . '</messageheaders>';
        $service = new MessageBatchesService($this->authentication, $this->fakeHttp('get', $xml, $url));

        $messages = $service->messages('batch-1');

        $this->assertSame('https://api.esendex.com/v1.0/messagebatches/batch-1/messages?startIndex=0&count=15', $url);
        $this->assertSame('msg-1', $messages[0]->id());
        $this->assertSame('Delivered', $messages[0]->status());
        $this->assertSame('33600000001', $messages[0]->phonenumber());
    }
}
