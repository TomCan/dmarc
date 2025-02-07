<?php

namespace TomCan\Dmarc;

use TomCan\Dmarc\Exception\DmarcInvalidFormatException;
use TomCan\Dmarc\Exception\DmarcMultipleRecordsException;
use TomCan\PublicSuffixList\PSLInterface;

class DmarcParser
{
    /** @var string[] */
    private $valid_tags = ['v', 'adkim', 'aspf', 'fo', 'p', 'pct', 'rf', 'ri', 'rua', 'ruf', 'sp'];

    private PSLInterface $psl;

    public function __construct(PSLInterface $psl)
    {
        $this->psl = $psl;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function query(string $domain): ?array
    {
        $records = $this->fetchDmarcRecords($domain);
        if (empty($records)) {
            // no dmarc record found for domain. Check organization domain if it's a subdomain
            $orgDomain = $this->getOrganizationDomain($domain);
            if ($orgDomain !== $domain) {
                $records = $this->fetchDmarcRecords($orgDomain);
            }
        }

        if (1 == count($records)) {
            // must have exactly 1 record
            return $this->validateRecord($records[0]);
        } elseif (0 == count($records)) {
            return null;
        } else {
            throw new DmarcMultipleRecordsException($records, 'Multiple DMARC records found');
        }
    }

    public function getOrganizationDomain(string $domain): string
    {
        $domainParts = explode('.', $domain);
        $tld = $this->psl->getTldOfDomain($domain) ?? '';
        $tldParts = explode('.', $tld);

        return implode('.', array_slice($domainParts, (-1 * count($tldParts)) - 1));
    }

    /**
     * @return array<array<string,mixed>>
     */
    private function fetchDmarcRecords(string $domain): array
    {
        $found = [];

        /** @var list<array>|false $records */
        $records = dns_get_record('_dmarc.'.$domain, DNS_TXT);
        if (false !== $records) {
            /** @var array<string,string> $record */
            foreach ($records as $record) {
                $parsed = $this->tryParsingAsDmarc((string) $record['txt']);
                if (null !== $parsed) {
                    $found[] = $parsed;
                }
            }
        }

        return $found;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function tryParsingAsDmarc(string $text): ?array
    {
        $record = [];
        $parts = explode(';', $text);
        foreach ($parts as $part) {
            $part = trim($part);
            if (0 == count($record) && 'v=DMARC1' != $part) {
                // not a valid DMARC record
                return null;
            } else {
                $valueTag = explode('=', $part, 2);
                if (2 == count($valueTag)) {
                    if (in_array($valueTag[0], $this->valid_tags)) {
                        // valid tag, add to parsed record
                        $record[$valueTag[0]] = trim($valueTag[1]);
                    }
                }
            }
        }

        return $record;
    }

    /**
     * @param array<string,mixed> $record
     *
     * @return array<string,mixed>
     */
    public function validateRecord(array $record): array
    {
        // check if values are valid
        $keys = array_keys($record);
        if (($keys[0] ?? '') != 'v' || 'DMARC1' != $record['v']) {
            // first key must be v and must have value DMARC1
            throw new DmarcInvalidFormatException('Record must start with "v=DMARC1"');
        } elseif (
            !in_array($record['p'] ?? 'invalid', ['none', 'quarantine', 'reject'])
            || (isset($record['sp']) && !in_array($record['sp'], ['none', 'quarantine', 'reject']))
        ) {
            // does not contain valid p-tags, or contains invalid sp-tag
            /*
               if a "rua" tag is present and contains at least one
               syntactically valid reporting URI, the Mail Receiver SHOULD
               act as if a record containing a valid "v" tag and "p=none"
               was retrieved, and continue processing;
            */
            // for now, just check for rua tag
            if (isset($record['rua'])) {
                $record['p'] = 'none';
            }
        }

        return $record;
    }
}
