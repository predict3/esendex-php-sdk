<?php

namespace Esendex\Compat;

use Esendex\Authentication\IAuthentication;
use Esendex\Authentication\LoginAuthentication;
use Esendex\DispatchService;
use Esendex\Exceptions\ArgumentException;
use Esendex\Exceptions\EsendexException;
use Esendex\Http\BadRequestException;
use Esendex\Http\HttpClient;
use Esendex\Http\IHttp;
use Esendex\Http\RequestTimedOutException;
use Esendex\Http\ResourceNotFoundException;
use Esendex\Http\ServerErrorException;
use Esendex\Http\UnauthorisedException;
use Esendex\MessageBatchesService;
use Esendex\MessageHeaderService;
use Esendex\Model\BatchesMessage;
use Esendex\Model\DispatchMessage;
use Esendex\Model\FromAddress;
use Esendex\Model\Message;
use Esendex\Model\MessageBody;
use Esendex\Model\OptOut;
use Esendex\Model\OptOutsPage;
use Esendex\Model\ResultItem;
use Esendex\Model\SentMessage;
use Esendex\Model\SentMessagesPage;
use Esendex\OptOutsService;
use Esendex\SentMessagesService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins the public API actually used by Predict projects (php-lib, unsubscribe-bundle and the applications
 * depending on them). If this test breaks, a consumer project breaks.
 *
 * Every HTTP call goes through a fake IHttp: nothing can be sent for real.
 */
class ConsumerContractTest extends TestCase
{
    private const ACCOUNT = 'EX0000000';

    private LoginAuthentication $authentication;

    protected function setUp(): void
    {
        $this->authentication = new LoginAuthentication(self::ACCOUNT, 'fake-login', 'fake-password');
    }

    /**
     * Fake IHttp returning $response for the given method and capturing the requested URL and body.
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

    private function failingHttp(string $method, \Throwable $exception): IHttp
    {
        $http = $this->createStub(IHttp::class);
        $http->method('isSecure')->willReturn(true);
        $http->method($method)->willThrowException($exception);

        return $http;
    }

    // ------------------------------------------------------------------
    // Signatures
    // ------------------------------------------------------------------

    public static function keptMethods(): array
    {
        return [
            'DispatchService::__construct' => [DispatchService::class, '__construct', ['authentication', 'httpClient', 'parser']],
            'DispatchService::send' => [DispatchService::class, 'send', ['message']],
            'DispatchService::sendMultiple' => [DispatchService::class, 'sendMultiple', ['messages']],
            'DispatchService::getCredits' => [DispatchService::class, 'getCredits', []],
            'OptOutsService::__construct' => [OptOutsService::class, '__construct', ['authentication', 'httpClient', 'parser']],
            'OptOutsService::add' => [OptOutsService::class, 'add', ['accountReference', 'mobileNumber']],
            'OptOutsService::getMyAccountReference' => [OptOutsService::class, 'getMyAccountReference', ['pageNumber', 'pageSize']],
            'MessageBatchesService::__construct' => [MessageBatchesService::class, '__construct', ['authentication', 'httpClient', 'parser']],
            'MessageBatchesService::messages' => [MessageBatchesService::class, 'messages', ['batch_id', 'startIndex', 'pageSize']],
            'MessageHeaderService::__construct' => [MessageHeaderService::class, '__construct', ['authentication', 'httpClient', 'parser']],
            'MessageHeaderService::message' => [MessageHeaderService::class, 'message', ['messageId']],
            'SentMessagesService::__construct' => [SentMessagesService::class, '__construct', ['authentication', 'httpClient', 'parser']],
            'SentMessagesService::loadMessages' => [SentMessagesService::class, 'loadMessages', ['options']],
            'HttpClient::__construct' => [HttpClient::class, '__construct', ['secure']],
            'HttpClient::get' => [HttpClient::class, 'get', ['url', 'authentication']],
            'LoginAuthentication::__construct' => [LoginAuthentication::class, '__construct', ['accountReference', 'username', 'password']],
            'DispatchMessage::__construct' => [DispatchMessage::class, '__construct', ['originator', 'recipient', 'body', 'type', 'validityPeriod', 'language', 'characterSet', 'retries']],
        ];
    }

    #[Test]
    #[DataProvider('keptMethods')]
    public function keptApiSignatures(string $class, string $method, array $parameterNames): void
    {
        $reflection = new \ReflectionMethod($class, $method);

        $this->assertTrue($reflection->isPublic());
        $this->assertSame($parameterNames, array_map(fn (\ReflectionParameter $p) => $p->getName(), $reflection->getParameters()));
        $this->assertFalse((new \ReflectionClass($class))->isFinal(), "$class must stay extendable/mockable");
    }

    #[Test]
    public function keptDefaultValues(): void
    {
        $defaults = fn (string $class, string $method) => array_map(
            fn (\ReflectionParameter $p) => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : '<required>',
            (new \ReflectionMethod($class, $method))->getParameters()
        );

        $this->assertSame([null, null], $defaults(OptOutsService::class, 'getMyAccountReference'));
        $this->assertSame(['<required>', 0, 15], $defaults(MessageBatchesService::class, 'messages'));
        $this->assertSame([true], $defaults(HttpClient::class, '__construct'));
        $this->assertSame(
            ['<required>', '<required>', '<required>', '<required>', 0, 'en-GB', null, null],
            $defaults(DispatchMessage::class, '__construct')
        );
    }

    #[Test]
    public function keptConstants(): void
    {
        $this->assertSame('SMS', Message::SmsType);
        $this->assertSame('Inbound', Message::Inbound);
        $this->assertSame('Outbound', Message::Outbound);
        $this->assertSame('GSM', MessageBody::CharsetGSM);
        $this->assertSame('Unicode', MessageBody::CharsetUnicode);
    }

    #[Test]
    public function keptExceptionHierarchy(): void
    {
        $this->assertTrue(is_subclass_of(ArgumentException::class, EsendexException::class));
        $this->assertTrue(is_subclass_of(BadRequestException::class, EsendexException::class));
        $this->assertTrue(is_subclass_of(EsendexException::class, \Exception::class));
        $this->assertTrue(is_subclass_of(LoginAuthentication::class, IAuthentication::class));
    }

    // ------------------------------------------------------------------
    // Sending (php-lib EsendexSender and applications)
    // ------------------------------------------------------------------

    private const DISPATCH_RESPONSE = '<?xml version="1.0" encoding="utf-8"?>'
        . '<messageheaders batchid="batch-1" xmlns="http://api.esendex.com/ns/">'
        . '<messageheader uri="https://api.esendex.com/v1.0/MessageHeaders/msg-1" id="msg-1" />'
        . '<messageheader uri="https://api.esendex.com/v1.0/MessageHeaders/msg-2" id="msg-2" />'
        . '</messageheaders>';

    #[Test]
    public function phpLibSendWithStringValidity(): void
    {
        $http = $this->fakeHttp('post', self::DISPATCH_RESPONSE, $url, $body);
        $service = new DispatchService($this->authentication, $http);

        // php-lib passes validity as the string '0' and several recipients joined by a comma
        $message = new DispatchMessage('Predict', '33600000001,33600000002', 'Alerte  ', Message::SmsType, '0', 'fr-FR', MessageBody::CharsetGSM);
        $result = $service->send($message);

        $this->assertInstanceOf(ResultItem::class, $result);
        $this->assertSame('msg-1', $result->id());
        $this->assertSame('batch-1', $result->batchid());
        $this->assertSame('https://api.esendex.com/v1.0/messagedispatcher', $url);

        $xml = simplexml_load_string($body);
        $this->assertSame(self::ACCOUNT, (string) $xml->accountreference);
        $this->assertSame('GSM', (string) $xml->characterset);
        $this->assertSame('Predict', (string) $xml->message->from);
        $this->assertSame('33600000001,33600000002', (string) $xml->message->to);
        $this->assertSame('SMS', (string) $xml->message->type);
        $this->assertSame('fr-FR', (string) $xml->message->lang);
        $this->assertFalse(isset($xml->message->validity), 'validity 0 must not be sent');
    }

    #[Test]
    public function phpLibSendMultiple(): void
    {
        $http = $this->fakeHttp('post', self::DISPATCH_RESPONSE, $url, $body);
        $service = new DispatchService($this->authentication, $http);

        $results = $service->sendMultiple([
            new DispatchMessage('Predict', '33600000001', 'txt', Message::SmsType, 0, 'fr-FR', MessageBody::CharsetGSM),
            new DispatchMessage('Predict', '33600000002', 'txt', Message::SmsType, 12, 'fr-FR', MessageBody::CharsetGSM),
        ]);

        $this->assertCount(2, $results);
        $this->assertSame(['msg-1', 'msg-2'], array_map(fn (ResultItem $r) => $r->id(), $results));
        $this->assertSame(['batch-1', 'batch-1'], array_map(fn (ResultItem $r) => $r->batchid(), $results));
        $this->assertCount(2, simplexml_load_string($body)->message);
        $this->assertSame('12', (string) simplexml_load_string($body)->message[1]->validity);
    }

    #[Test]
    public function sendMultipleWithoutMessageIsArgumentException(): void
    {
        $service = new DispatchService($this->authentication, $this->fakeHttp('post', self::DISPATCH_RESPONSE));

        $this->expectException(ArgumentException::class);
        $service->sendMultiple([]);
    }

    #[Test]
    public function sendWithEmptyRecipientIsArgumentException(): void
    {
        $service = new DispatchService($this->authentication, $this->fakeHttp('post', self::DISPATCH_RESPONSE));

        // php-lib classifies ArgumentException as "not attempted"
        $this->expectException(ArgumentException::class);
        $service->send(new DispatchMessage('Predict', '', 'txt', Message::SmsType, '0', 'fr-FR', MessageBody::CharsetGSM));
    }

    #[Test]
    public function sendWithEmptyDispatchResultIsEsendexException(): void
    {
        $empty = '<?xml version="1.0" encoding="utf-8"?><messageheaders batchid="b" xmlns="http://api.esendex.com/ns/" />';
        $service = new DispatchService($this->authentication, $this->fakeHttp('post', $empty));

        $this->expectException(EsendexException::class);
        $service->send(new DispatchMessage('Predict', '33600000001', 'txt', Message::SmsType));
    }

    public static function httpErrors(): array
    {
        // HTTP exceptions are declared in IHttp.php: they only exist once the interface is loaded
        interface_exists(IHttp::class);

        return [
            '400' => [new BadRequestException('bad', 400), 400],
            '401' => [new UnauthorisedException('unauthorised', 401), 401],
            '404' => [new ResourceNotFoundException('not found', 404), 404],
            '408' => [new RequestTimedOutException('timeout', 408), 408],
            '500' => [new ServerErrorException('error', 500), 500],
        ];
    }

    #[Test]
    #[DataProvider('httpErrors')]
    public function httpErrorCodesPreserved(EsendexException $exception, int $code): void
    {
        $service = new DispatchService($this->authentication, $this->failingHttp('post', $exception));

        try {
            $service->send(new DispatchMessage('Predict', '33600000001', 'txt', Message::SmsType));
            $this->fail('An exception was expected');
        } catch (EsendexException $e) {
            // php-lib: 4xx except 408 => rejected, anything else => unknown
            $this->assertSame($code, $e->getCode());
            $this->assertSame(get_class($exception), get_class($e));
        }
    }

    #[Test]
    public function unknownHttpStatusIsPlainExceptionWithCode(): void
    {
        $method = new \ReflectionMethod(HttpClient::class, 'getHttpException');

        $exception = $method->invoke(new HttpClient(), ['statuscode' => 418, 'data' => '']);
        $this->assertSame(\Exception::class, get_class($exception));
        $this->assertSame(418, $exception->getCode());

        $known = $method->invoke(new HttpClient(), ['statuscode' => 503, 'data' => 'down']);
        $this->assertInstanceOf(EsendexException::class, $known);
        $this->assertSame(503, $known->getCode());
        $this->assertSame('down', $known->getMessage());
    }

    // ------------------------------------------------------------------
    // Credits (health checks)
    // ------------------------------------------------------------------

    private const ACCOUNTS_RESPONSE = '<?xml version="1.0" encoding="utf-8"?>'
        . '<accounts xmlns="http://api.esendex.com/ns/">'
        . '<account id="a1"><reference>EX9999999</reference><messagesremaining>5</messagesremaining></account>'
        . '<account id="a2"><reference>ex0000000</reference><messagesremaining>1234</messagesremaining></account>'
        . '</accounts>';

    #[Test]
    public function getCreditsMatchingAccount(): void
    {
        $service = new DispatchService($this->authentication, $this->fakeHttp('get', self::ACCOUNTS_RESPONSE, $url));

        $this->assertSame(1234, $service->getCredits());
        $this->assertSame('https://api.esendex.com/v1.0/accounts', $url);
    }

    #[Test]
    public function getCreditsMissingAccountReturnsZero(): void
    {
        $other = new LoginAuthentication('EX1111111', 'fake', 'fake');
        $service = new DispatchService($other, $this->fakeHttp('get', self::ACCOUNTS_RESPONSE));

        $this->assertSame(0, $service->getCredits());
    }

    #[Test]
    public function getCreditsHttpErrorReturnsZero(): void
    {
        $service = new DispatchService($this->authentication, $this->failingHttp('get', new UnauthorisedException('no', 401)));

        $this->assertSame(0, $service->getCredits());
    }

    // ------------------------------------------------------------------
    // Opt-outs (unsubscribe-bundle, php-lib health check, AddOptout commands)
    // ------------------------------------------------------------------

    private const OPTOUTS_RESPONSE = '<?xml version="1.0" encoding="utf-8"?>'
        . '<optouts startindex="25" count="2" totalcount="52" xmlns="http://api.esendex.com/ns/">'
        . '<optout id="o-1"><accountreference>EX0000000</accountreference>'
        . '<receivedat>2026-01-02T10:11:12.123Z</receivedat><from><phonenumber>33600000001</phonenumber></from></optout>'
        . '<optout id="o-2"><accountreference>EX0000000</accountreference>'
        . '<receivedat>2026-01-03T10:11:12Z</receivedat></optout>'
        . '</optouts>';

    #[Test]
    public function optOutsPaginationAsUnsubscribeBundle(): void
    {
        $service = new OptOutsService($this->authentication, $this->fakeHttp('get', self::OPTOUTS_RESPONSE, $url));

        $page = $service->getMyAccountReference(2, 25);

        $this->assertSame('https://api.esendex.com/v1.0/optouts?startIndex=25&count=25&accountreference=EX0000000', $url);
        $this->assertInstanceOf(OptOutsPage::class, $page);
        $this->assertSame(52, $page->totalCount());
        $this->assertSame(25, $page->startIndex());
        $this->assertSame(2, $page->count());

        $first = $page->optOuts()[0];
        $this->assertInstanceOf(OptOut::class, $first);
        $this->assertSame('o-1', $first->id());
        $this->assertSame('EX0000000', $first->accountReference());
        $this->assertInstanceOf(FromAddress::class, $first->from());
        $this->assertSame('33600000001', $first->from()->phoneNumber());
        $this->assertInstanceOf(\DateTime::class, $first->receivedAt());
        $this->assertSame('2026-01-02 10:11:12', $first->receivedAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function optOutsDefaultPaginationAsHealthCheck(): void
    {
        $service = new OptOutsService($this->authentication, $this->fakeHttp('get', self::OPTOUTS_RESPONSE, $url));

        $service->getMyAccountReference();
        $this->assertSame('https://api.esendex.com/v1.0/optouts?startIndex=0&count=15&accountreference=EX0000000', $url);

        $service->getMyAccountReference(1, 1);
        $this->assertSame('https://api.esendex.com/v1.0/optouts?startIndex=0&count=1&accountreference=EX0000000', $url);
    }

    #[Test]
    public function optOutWithoutFromNumber(): void
    {
        $service = new OptOutsService($this->authentication, $this->fakeHttp('get', self::OPTOUTS_RESPONSE));

        $second = $service->getMyAccountReference(1, 25)->optOuts()[1];

        // Current behaviour: an empty FromAddress, not null (consumers call from()->phoneNumber() without null check)
        $this->assertSame('o-2', $second->id());
        $this->assertInstanceOf(FromAddress::class, $second->from());
        $this->assertNull($second->from()->phoneNumber());
    }

    #[Test]
    public function addOptOutAsClientCommands(): void
    {
        $response = '<?xml version="1.0" encoding="utf-8"?>'
            . '<response xmlns="http://api.esendex.com/ns/"><optout id="o-9">'
            . '<accountreference>EX0000000</accountreference><receivedat>2026-02-01T08:00:00Z</receivedat>'
            . '<from><phonenumber>33600000009</phonenumber></from></optout></response>';
        $service = new OptOutsService($this->authentication, $this->fakeHttp('post', $response, $url, $body));

        $optOut = $service->add('EX0000000', '33600000009');

        $this->assertSame('https://api.esendex.com/v1.0/optouts', $url);
        $request = simplexml_load_string($body);
        $this->assertSame('EX0000000', (string) $request->accountreference);
        $this->assertSame('33600000009', (string) $request->from->phonenumber);

        $this->assertInstanceOf(OptOut::class, $optOut);
        $this->assertSame('o-9', $optOut->id());
        $this->assertSame('33600000009', $optOut->from()->phoneNumber());
    }

    #[Test]
    public function addOptOutWithoutNumberIsArgumentException(): void
    {
        $service = new OptOutsService($this->authentication, $this->fakeHttp('post', ''));

        $this->expectException(ArgumentException::class);
        $service->add('EX0000000', '');
    }

    // ------------------------------------------------------------------
    // Tracking (php-lib checkDelivery / getDeliveryInfosForMessage / getMessagesSentInInterval, applications)
    // ------------------------------------------------------------------

    private const HEADER = '<messageheader id="%s" uri="https://api.esendex.com/v1.0/messageheaders/%1$s">'
        . '<reference>EX0000000</reference><status>%s</status>'
        . '<laststatusat>2026-03-01T10:00:05.123Z</laststatusat><submittedat>2026-03-01T10:00:00.1Z</submittedat>'
        . '<sentat>2026-03-01T10:00:01Z</sentat><deliveredat>2026-03-01T10:00:04Z</deliveredat>'
        . '<type>SMS</type><to><phonenumber>33600000001</phonenumber></to><from><phonenumber>Predict</phonenumber></from>'
        . '<summary>Alerte</summary><body uri="https://api.esendex.com/v1.0/messageheaders/%1$s/body" />'
        . '<direction>Outbound</direction><parts>1</parts><username>user</username><batch id="batch-1" />'
        . '</messageheader>';

    #[Test]
    public function messageHeaderAccessors(): void
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . str_replace('<messageheader ', '<messageheader xmlns="http://api.esendex.com/ns/" ', sprintf(self::HEADER, 'msg-1', 'Delivered'));
        $service = new MessageHeaderService($this->authentication, $this->fakeHttp('get', $xml, $url));

        $message = $service->message('msg-1');

        $this->assertSame('https://api.esendex.com/v1.0/messageheaders/msg-1', $url);
        $this->assertInstanceOf(SentMessage::class, $message);
        $this->assertSame('msg-1', $message->id());
        $this->assertSame('batch-1', $message->batchId());
        $this->assertSame('Delivered', $message->status());
        $this->assertSame('33600000001', $message->recipient());
        $this->assertSame('Predict', $message->originator());
        $this->assertSame('2026-03-01 10:00:04', $message->deliveredAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function sentMessagesPageIteration(): void
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<messageheaders startindex="100" count="2" totalcount="230" xmlns="http://api.esendex.com/ns/">'
            . sprintf(self::HEADER, 'msg-1', 'Delivered') . sprintf(self::HEADER, 'msg-2', 'Failed')
            . '</messageheaders>';
        $service = new SentMessagesService($this->authentication, $this->fakeHttp('get', $xml, $url));

        // Same options as php-lib EsendexSender::getMessagesSentInInterval (int startIndex / count)
        $page = $service->loadMessages([
            'start' => '2026-03-01T00:00:00+00:00',
            'finish' => '2026-04-01T00:00:00+00:00',
            'startIndex' => 100,
            'count' => 100,
        ]);

        $this->assertSame(
            'https://api.esendex.com/v1.0/messageheaders?start=2026-03-01T00%3A00%3A00%2B00%3A00&finish=2026-04-01T00%3A00%3A00%2B00%3A00&startIndex=100&count=100&accountreference=EX0000000',
            $url
        );
        $this->assertInstanceOf(SentMessagesPage::class, $page);
        $this->assertSame(230, $page->totalCount());
        $this->assertSame(100, $page->startIndex());
        $this->assertSame(2, $page->count());

        $seen = [];
        while ($page->valid()) {
            $message = $page->current();
            $seen[] = [$message->id(), $message->batchId(), $message->status()];
            $page->next();
        }
        $this->assertSame([['msg-1', 'batch-1', 'Delivered'], ['msg-2', 'batch-1', 'Failed']], $seen);
        $this->assertSame('msg-1', $page[0]->id());
    }

    #[Test]
    public function emptySentMessagesPage(): void
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<messageheaders startindex="0" count="0" totalcount="0" xmlns="http://api.esendex.com/ns/" />';
        $service = new SentMessagesService($this->authentication, $this->fakeHttp('get', $xml));

        $page = $service->loadMessages(['startIndex' => 0, 'count' => 15]);

        $this->assertSame(0, $page->count());
        $this->assertSame(0, $page->totalCount());
        $this->assertFalse($page->valid());
        $this->assertNull($page->current());
    }

    #[Test]
    public function batchMessages(): void
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<messageheaders startindex="0" count="2" totalcount="2" xmlns="http://api.esendex.com/ns/">'
            . sprintf(self::HEADER, 'msg-1', 'Delivered') . sprintf(self::HEADER, 'msg-2', 'Failed')
            . '</messageheaders>';
        $service = new MessageBatchesService($this->authentication, $this->fakeHttp('get', $xml, $url));

        $messages = $service->messages('batch-1');

        $this->assertSame('https://api.esendex.com/v1.0/messagebatches/batch-1/messages?startIndex=0&count=15', $url);
        $this->assertIsArray($messages);
        $this->assertCount(2, $messages);
        $this->assertInstanceOf(BatchesMessage::class, $messages[0]);
        $this->assertSame('msg-1', $messages[0]->id());
        $this->assertSame('Delivered', $messages[0]->status());
        $this->assertSame('33600000001', $messages[0]->phonenumber());
    }
}
