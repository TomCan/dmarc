<?php

namespace TomCan\Dmarc\Exception;

class DmarcMultipleRecordsException extends DmarcException
{
    /** @var array<array<string,mixed>> */
    private array $records;

    /**
     * @param array<array<string,mixed>> $records
     */
    public function __construct(array $records, string $message = '', int $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->records = $records;
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function getRecords(): array
    {
        return $this->records;
    }
}
