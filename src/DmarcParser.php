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
     * @return array<array<string,string>>
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
     * @return array<string,string>|null
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
     * @param array<string,string> $record
     *
     * @return array<string,mixed>
     *
     * @throws DmarcInvalidFormatException
     */
    public function validateRecord(array $record): array
    {
        $uriParser = new DmarcUriParser();
        // check if values are valid
        $keys = array_keys($record);
        if (($keys[0] ?? '') != 'v' || 'DMARC1' != $record['v']) {
            // first key must be v and must have value DMARC1
            throw new DmarcInvalidFormatException('Record must start with "v=DMARC1"');
        } elseif (
            // Does not contain valid p-tags, or contains invalid sp-tag.
            !in_array($record['p'] ?? 'invalid', ['none', 'quarantine', 'reject'])
            || (isset($record['sp']) && !in_array($record['sp'], ['none', 'quarantine', 'reject']))
        ) {
            // If rua tag with at least 1 valid URI, then set p=none instead.
            if (isset($record['rua']) && !empty($uriParser->parseAll($record['rua'], true))) {
                $record['p'] = 'none';
            } else {
                throw new DmarcInvalidFormatException('Invalid or missing policy, or invalid sp, and no rua tag with valid reporting URI');
            }
        }

        // test other tags, regexp based
        foreach (['aspf' => '^[rs]$', 'adkim' => '^[rs]$', 'fo' => '^[01ds]$', 'p' => '^(none|quarantine|reject)$', 'sp' => '^(none|quarantine|reject)$', 'rf' => '^afrf$'] as $key => $regexp) {
            if (isset($record[$key]) && !preg_match('/'.$regexp.'/', $record[$key])) {
                throw new DmarcInvalidFormatException('Invalid value for '.$key);
            }
        }

        // test numeric tags
        foreach (['pct' => [1, 100], 'ri' => [1, 4294967295]] as $key => $range) {
            if (isset($record[$key])) {
                $value = intval($record[$key]);
                if (strval($value) != $record[$key]) {
                    // value given is not strict integer, like 1.0, or 2.5
                    throw new DmarcInvalidFormatException('Invalid value for '.$key);
                } elseif ($value < $range[0] || $value > $range[1]) {
                    throw new DmarcInvalidFormatException('Invalid value for '.$key);
                }
            }
        }

        // test uri tags
        foreach (['rua', 'ruf'] as $key) {
            if (isset($record[$key])) {
                if (0 == count($uriParser->parseAll($record[$key], true))) {
                    throw new DmarcInvalidFormatException('No valid uris found in '.$key);
                }
            }
        }

        // we got here, return the record
        return $record;
    }
}
