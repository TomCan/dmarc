<?php

namespace Tests\TomCan\Dmarc;

use PHPUnit\Framework\TestCase;
use TomCan\Dmarc\DmarcParser;
use TomCan\Dmarc\Exception\DmarcInvalidFormatException;
use TomCan\PublicSuffixList\PSLInterface;

class DmarcParserTest extends TestCase
{
    private DmarcParser $dmarcParser;
    private PSLInterface $pslMock;

    protected function setUp(): void
    {
        $this->pslMock = $this->createMock(PSLInterface::class);
        $this->dmarcParser = new DmarcParser($this->pslMock);
    }

    /**
     * @dataProvider domainProvider
     */
    public function testGetOrganizationDomain(string $input, string $tld, string $expected): void
    {
        $this->pslMock->method('getTldOfDomain')->willReturn($tld);

        $result = $this->dmarcParser->getOrganizationDomain($input);
        $this->assertEquals($expected, $result);
    }

    public function domainProvider(): array
    {
        return [
            ['tom.be', 'be', 'tom.be'],
            ['mt.tom.be', 'be', 'tom.be'],
            ['server1.mt.tom.be', 'be', 'tom.be'],
        ];
    }

    /**
     * @dataProvider validRecordProvider
     */
    public function testValidateRecordWithValidInput(array $input, array $expected): void
    {
        $result = $this->dmarcParser->validateRecord($input);
        $this->assertEquals($expected, $result);
    }

    public function validRecordProvider(): array
    {
        return [
            [
                ['v' => 'DMARC1', 'p' => 'none'],
                ['v' => 'DMARC1', 'p' => 'none'],
            ],
            [
                ['v' => 'DMARC1', 'p' => 'quarantine', 'sp' => 'reject'],
                ['v' => 'DMARC1', 'p' => 'quarantine', 'sp' => 'reject'],
            ],
            [
                ['v' => 'DMARC1', 'rua' => 'mailto:dmarc@example.com'],
                ['v' => 'DMARC1', 'rua' => 'mailto:dmarc@example.com', 'p' => 'none'],
            ],
        ];
    }

    public function testValidateRecordWithInvalidVersion(): void
    {
        $this->expectException(DmarcInvalidFormatException::class);
        $this->expectExceptionMessage('Record must start with "v=DMARC1"');

        $this->dmarcParser->validateRecord(['v' => 'DMARC2', 'p' => 'none']);
    }

    public function testValidateRecordWithVersionNotFirst(): void
    {
        $this->expectException(DmarcInvalidFormatException::class);
        $this->expectExceptionMessage('Record must start with "v=DMARC1"');

        $this->dmarcParser->validateRecord(['p' => 'none', 'v' => 'DMARC1']);
    }

    public function testValidateRecordWithInvalidPolicyAndNoRua(): void
    {
        $this->expectException(DmarcInvalidFormatException::class);

        $result = $this->dmarcParser->validateRecord(['v' => 'DMARC1', 'p' => 'invalid']);
    }

    public function testValidateRecordWithInvalidSubdomainPolicyAndNoRua(): void
    {
        $this->expectException(DmarcInvalidFormatException::class);

        $result = $this->dmarcParser->validateRecord(['v' => 'DMARC1', 'p' => 'reject', 'sp' => 'invalid']);
    }

    public function testValidateRecordWithInvalidPolicyButWithValidRua(): void
    {
        $result = $this->dmarcParser->validateRecord(['v' => 'DMARC1', 'p' => 'invalid', 'rua' => 'mailto:dmarc@example.com']);
        $this->assertEquals('none', $result['p']);
    }
}
