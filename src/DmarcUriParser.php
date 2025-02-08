<?php

namespace TomCan\Dmarc;

use TomCan\Dmarc\Exception\DmarcInvalidFormatException;

class DmarcUriParser
{
    /**
     * @return array<array{'scheme': string, 'target': string, 'limit': string}>
     *
     * @throws DmarcInvalidFormatException
     */
    public function parseAll(string $uris): array
    {
        $result = [];

        $parts = explode(',', $uris);
        foreach ($parts as $uri) {
            $matches = [];
            if (preg_match('/^([a-z0-9]+):([^,!]+)(?:!([0-9]+[kmgt]?))?$/', $uri, $matches)) {
                $result[] = [
                    'scheme' => $matches[1],
                    'target' => $matches[2],
                    'limit' => $matches[3] ?? '',
                ];
            } else {
                throw new DmarcInvalidFormatException('Invalid URI format');
            }
        }

        return $result;
    }

    /**
     * @return array{'scheme': string, 'target': string, 'limit': string}
     *
     * @throws DmarcInvalidFormatException
     */
    public function parseOne(string $uri): array
    {
        $results = $this->parseAll($uri);
        if (count($results) > 1) {
            throw new DmarcInvalidFormatException('Multiple URIs found');
        } else {
            return $results[0];
        }
    }
}
