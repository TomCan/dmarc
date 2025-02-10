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

    public function invalidRecordProvider(): array
    {
        return [
            [
                ['v' => 'DMARC2'],
                'DMARC2 is invalid DMARC version',
            ],
            [
                ['v' => 'DMARC1', 'p' => 'noon'],
                'noon is invalid policy and no rua given',
            ],
            [
                ['v' => 'DMARC1', 'p' => 'noon', 'rua' => 'dmarc@example.com'],
                'noon is invalid policy and no valid URI in rua',
            ],
            [
                ['v' => 'DMARC1', 'p' => 'noon', 'rua' => 'dmarc@example.com'],
                'noon is invalid policy and no valid URI in rua',
            ],
            [
                ['v' => 'DMARC1', 'p' => 'none', 'sp' => 'noon'],
                'noon is invalid subdomain policy and no valid URI in rua',
            ],
            [
                ['v' => 'DMARC1', 'p' => 'none', 'sp' => 'noon', 'rua' => 'dmarc@example.com'],
                'noon is invalid policy and no valid URI in rua',
            ],
            [
                ['v' => 'DMARC1', 'aspf' => 'strict'],
                'aspf does not contain a valid value',
            ],
            [
                ['v' => 'DMARC1', 'adkim' => 'strict'],
                'adkim does not contain a valid value',
            ],
            [
                ['v' => 'DMARC1', 'fo' => '2'],
                'fo does not contain a valid value',
            ],
            [
                ['v' => 'DMARC1', 'rf' => 'abcd'],
                'rf is not afrf',
            ],
            [
                ['v' => 'DMARC1', 'pct' => '0'],
                'pct is not larger than 0',
            ],
            [
                ['v' => 'DMARC1', 'pct' => '-5'],
                'pct is not larger than 0',
            ],
            [
                ['v' => 'DMARC1', 'pct' => '101'],
                'pct is larger than 100',
            ],
            [
                ['v' => 'DMARC1', 'pct' => '50.1'],
                'pct is not an integer',
            ],
            [
                ['v' => 'DMARC1', 'pct' => 'fifty'],
                'pct is not an integer',
            ],
            [
                ['v' => 'DMARC1', 'ri' => '0'],
                'ri is not larger than 0',
            ],
            [
                ['v' => 'DMARC1', 'ri' => '4294967296'],
                'ri is larger than uint32',
            ],
            [
                ['v' => 'DMARC1', 'ri' => ''],
                'ri is empty',
            ],
            [
                ['v' => 'DMARC1', 'rua' => 'dmarc@example.com'],
                'rua does not contain valid uri',
            ],
            [
                ['v' => 'DMARC1', 'rua' => 'dmarc@example.com,dmorc@example.com,dmirc@example.com'],
                'rua does not contain valid uri',
            ],
            [
                ['v' => 'DMARC1', 'ruf' => 'dmarc@example.com'],
                'ruf does not contain valid uri',
            ],
            [
                ['v' => 'DMARC1', 'ruf' => 'dmarc@example.com,dmorc@example.com,dmirc@example.com'],
                'ruf does not contain valid uri',
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

    /**
     * @dataProvider invalidRecordProvider
     */
    public function testValidateRecordWithInvalidRecords(array $input, string $message): void
    {
        $exceptionThrown = false;
        try {
            $this->dmarcParser->validateRecord($input);
        } catch (DmarcInvalidFormatException $e) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown, $message);
    }
}
