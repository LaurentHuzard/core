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

        // (Optional) validate via XmlEncoder to keep using it in the pipeline
        $decodeContext = ['remove_empty_tags' => false];
        $this->xmlEncoder->decode($expectedXml, 'xml', $decodeContext);
        $this->xmlEncoder->decode($actualXml, 'xml', $decodeContext);

        $expectedC14n = $this->canonicalizeXmlOrderInsensitive($expectedXml);
        $actualC14n = $this->canonicalizeXmlOrderInsensitive($actualXml);

        $this->assertEquals(
            $expectedC14n,
            $actualC14n,
            "The XML is equal to (order-insensitive):\n{$actualC14n}"
        );
    }

    /**
     * Load XML, remove ignorable whitespace, sort children of every element
     * deterministically (by tag name, then by serialized attributes), then C14N.
     */
    private function canonicalizeXmlOrderInsensitive(string $xml): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;

        $opts = \LIBXML_NONET | \LIBXML_NOBLANKS | \LIBXML_NOERROR | \LIBXML_NOWARNING | \LIBXML_NOCDATA;
        if (!@$dom->loadXML($xml, $opts)) {
            throw new \RuntimeException("Invalid XML provided:\n".$xml);
        }

        if ($dom->documentElement) {
            $this->sortElementChildrenRecursively($dom->documentElement);
        }

        $dom->normalizeDocument();

        $c14n = $dom->C14N(false, false); // inclusive, no comments
        if (false !== $c14n) {
            return $c14n;
        }

        $out = $dom->saveXML($dom->documentElement, \LIBXML_NOEMPTYTAG);

        return false === $out ? '' : $out;
    }

    /**
     * Recursively sort only ELEMENT_NODE children by (localName, attributes string, inner text).
     * Keeps non-element nodes (text, cdata) in place; NOBLANKS removes ignorable whitespace.
     */
    private function sortElementChildrenRecursively(\DOMElement $el): void
    {
        // Recurse first so deeper trees become stable
        for ($n = $el->firstChild; $n; $n = $n->nextSibling) {
            if ($n instanceof \DOMElement) {
                $this->sortElementChildrenRecursively($n);
            }
        }

        // Collect element children
        $elements = [];
        $others = []; // text, comments, etc.
        for ($n = $el->firstChild; $n; $n = $n->nextSibling) {
            if ($n instanceof \DOMElement) {
                $elements[] = $n;
            } else {
                $others[] = $n;
            }
        }

        if (\count($elements) <= 1) {
            return;
        }

        // Stable sort: by tag localName, then serialized attributes, then textContent
        usort($elements, static function (\DOMElement $a, \DOMElement $b): int {
            $na = $a->localName ?? $a->nodeName;
            $nb = $b->localName ?? $b->nodeName;
            if ($na !== $nb) {
                return $na <=> $nb;
            }

            $aa = $a->attributes ? self::serializeAttributes($a) : '';
            $ab = $b->attributes ? self::serializeAttributes($b) : '';
            if ($aa !== $ab) {
                return $aa <=> $ab;
            }

            // last tie-breaker: text content (trimmed)
            return trim($a->textContent) <=> trim($b->textContent);
        });

        // Remove all children then re-append in deterministic order:
        // - First non-element nodes in original order
        // - Then sorted element nodes
        while ($el->firstChild) {
            $el->removeChild($el->firstChild);
        }
        foreach ($others as $n) {
            $el->appendChild($n);
        }
        foreach ($elements as $n) {
            $el->appendChild($n);
        }
    }

    private static function serializeAttributes(\DOMElement $el): string
    {
        if (!$el->hasAttributes()) {
            return '';
        }
        $pairs = [];
        /** @var \DOMAttr $attr */
        foreach (iterator_to_array($el->attributes) as $attr) {
            $pairs[$attr->name] = $attr->value;
        }
        ksort($pairs);
        $out = [];
        foreach ($pairs as $k => $v) {
            $out[] = $k.'='.$v;
        }

        return implode(';', $out);
    }
}
