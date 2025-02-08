<?php

namespace Tests\TomCan\Dmarc;

use PHPUnit\Framework\TestCase;
use TomCan\Dmarc\DmarcUriParser;
use TomCan\Dmarc\Exception\DmarcInvalidFormatException;

class DmarcUriParserTest extends TestCase
{
    private DmarcUriParser $dmarcUriParser;

    protected function setUp(): void
    {
        $this->dmarcUriParser = new DmarcUriParser();
    }

    /**
     * @dataProvider validUriProvider
     */
    public function testValidSingleUris(string $input, array $expected): void
    {
        $result = $this->dmarcUriParser->parseOne($input);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider invalidUriProvider
     */
    public function testInvalidSingleUris(string $input): void
    {
        $this->expectException(DmarcInvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');

        $this->dmarcUriParser->parseOne($input);
    }

    public function testValidMultipleUris(): void
    {
        $data = $this->validUriProvider();
        $multiple = '';
        $expected = [];
        foreach ($data as $uri) {
            $multiple .= ','.$uri[0];
            $expected[] = $uri[1];
        }
        // strip leading ,
        $multiple = substr($multiple, 1);

        $results = $this->dmarcUriParser->parseAll($multiple);
        foreach ($results as $index => $result) {
            $this->assertEquals($expected[$index], $result);
        }
    }

    public function testInvalidMultipleUris(): void
    {
        $this->expectException(DmarcInvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');

        $valid = $this->validUriProvider();
        $invalid = $this->invalidUriProvider();
        $multiple = $valid[0][0].','.$invalid[0][0];
        $results = $this->dmarcUriParser->parseAll($multiple);
    }

    public function validUriProvider(): array
    {
        return [
            ['mailto:mot@tom.be', ['scheme' => 'mailto', 'target' => 'mot@tom.be', 'limit' => '']],
            ['mailto:mot@tom.be!10', ['scheme' => 'mailto', 'target' => 'mot@tom.be', 'limit' => '10']],
            ['mailto:mot@tom.be!10m', ['scheme' => 'mailto', 'target' => 'mot@tom.be', 'limit' => '10m']],
            ['custom:TomCan!007t', ['scheme' => 'custom', 'target' => 'TomCan', 'limit' => '007t']],
        ];
    }

    public function invalidUriProvider(): array
    {
        return [
            [':'],
            ['mailto:'],
            [':mot@tom.be'],
            ['mailto:mot@tom,be'],
            ['mail to:mot@tom.be'],
            ['mailto:mot@tom.be!10z'],
            ['custom:TomCan!w00t'],
        ];
    }
}
