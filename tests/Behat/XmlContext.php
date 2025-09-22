<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Tests\Behat;

use Behat\Gherkin\Node\PyStringNode;
use Behatch\Context\XmlContext as BaseXmlContext;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

final class XmlContext extends BaseXmlContext
{
    private readonly XmlEncoder $xmlEncoder;

    public function __construct()
    {
        $this->xmlEncoder = new XmlEncoder();
    }

    /**
     * @Then the XML should be equal to:
     */
    public function theXmlShouldBeEqualTo(PyStringNode $content): void
    {
        $expectedXml = (string) $content;
        $actualXml = $this->getSession()->getPage()->getContent();

        $decodeContext = [
            'remove_empty_tags' => false,
        ];
        $expectedArr = $this->xmlEncoder->decode($expectedXml, 'xml', $decodeContext);
        $actualArr = $this->xmlEncoder->decode($actualXml, 'xml', $decodeContext);

        // Optional normalization to avoid null/'' mismatches after decode
        $expectedArr = $this->normalizePhpFromXml($expectedArr);
        $actualArr = $this->normalizePhpFromXml($actualArr);

        // 2) Re-encode with the SAME encoder & options to stabilize output shape
        $encodeContext = [
            'format_output' => false,
            'remove_empty_tags' => false,
        ];
        $expectedStableXml = $this->xmlEncoder->encode($expectedArr, 'xml', $encodeContext);
        $actualStableXml = $this->xmlEncoder->encode($actualArr, 'xml', $encodeContext);

        // 3) Canonicalize via DOM C14N and compare
        $expectedC14n = $this->canonicalizeXml($expectedStableXml);
        $actualC14n = $this->canonicalizeXml($actualStableXml);

        $this->assertEquals(
            $expectedC14n,
            $actualC14n,
            "The XML is equal to:\n{$actualC14n}"
        );
    }

    private function canonicalizeXml(string $xml): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;

        $opts = \LIBXML_NONET | \LIBXML_NOBLANKS | \LIBXML_NOERROR | \LIBXML_NOWARNING | \LIBXML_NOCDATA;
        if (!@$dom->loadXML($xml, $opts)) {
            throw new \RuntimeException("Invalid XML provided:\n".$xml);
        }
        $dom->normalizeDocument();

        $c14n = $dom->C14N(false, false);
        if (false !== $c14n) {
            return $c14n;
        }

        $out = $dom->saveXML($dom->documentElement, \LIBXML_NOEMPTYTAG);

        return false === $out ? '' : $out;
    }

    /**
     * Normalize values produced by XmlEncoder to avoid null/'' and list/dict drift.
     */
    private function normalizePhpFromXml(mixed $value): mixed
    {
        if (\is_array($value)) {
            // Sort keys for deterministic re-encode and normalize children
            $isList = array_keys($value) === range(0, \count($value) - 1);
            if (!$isList) {
                ksort($value);
            }
            foreach ($value as $k => $v) {
                $value[$k] = $this->normalizePhpFromXml($v);
            }

            return $value;
        }

        // Treat null vs empty string as equivalent for empty XML elements
        if (null === $value) {
            return '';
        }

        return $value;
    }
}
