<?php

namespace Esendex;

use Esendex\Authentication\LoginAuthentication;
use Esendex\Http\HttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guarantees the test suite can never reach a real API (and therefore never send a real SMS).
 */
class SafetyNetTest extends TestCase
{
    #[Test]
    public function proxyEnvironmentIsDead(): void
    {
        $this->assertSame('http://127.0.0.1:9', getenv('https_proxy'));
        $this->assertSame('http://127.0.0.1:9', getenv('http_proxy'));
        $this->assertSame('http://127.0.0.1:9', getenv('ALL_PROXY'));
    }

    #[Test]
    public function realHttpClientCannotReachNetwork(): void
    {
        $client = new HttpClient();

        // http_code 0 means curl never got an HTTP response: the dead proxy refused the connection.
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(0);

        $client->get('https://example.com/', new LoginAuthentication('EX0000000', 'fake', 'fake'));
    }
}
