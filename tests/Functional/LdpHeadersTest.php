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

namespace ApiPlatform\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Tests\Fixtures\TestBundle\Entity\LdpDummy;
use ApiPlatform\Tests\RecreateSchemaTrait;
use ApiPlatform\Tests\SetupClassResourcesTrait;

final class LdpHeadersTest extends ApiTestCase
{
    use RecreateSchemaTrait;
    use SetupClassResourcesTrait;

    protected static ?bool $alwaysBootKernel = false;

    /**
     * @return class-string[]
     */
    public static function getResources(): array
    {
        return [
            LdpDummy::class,
        ];
    }

    public function testCollectionAllowsAndAcceptsPost(): void
    {
        $this->recreateSchema(self::getResources());

        self::createClient()->request('GET', '/ldp_dummies');

        self::assertResponseIsSuccessful();

        $headers = array_change_key_case(self::getHttpResponse()->getHeaders(false));

        self::assertSame('GET, POST', $headers['allow'][0]);
        self::assertSame('application/ld+json, application/json, text/turtle', $headers['accept-post'][0]);
    }

    public function testItemAllowsNonPostOperations(): void
    {
        $this->recreateSchema(self::getResources());

        self::createClient()->request('POST', '/ldp_dummies', ['json' => ['name' => 'Foo']]);
        self::assertResponseStatusCodeSame(201);

        $iri = $this->findIriBy(LdpDummy::class, ['name' => 'Foo']);

        self::createClient()->request('GET', $iri ?? '/ldp_dummies/1');

        self::assertResponseIsSuccessful();

        $headers = array_change_key_case(self::getHttpResponse()->getHeaders(false));

        self::assertSame('DELETE, GET, PATCH, PUT', $headers['allow'][0]);
        self::assertArrayNotHasKey('accept-post', $headers);
    }

    public function testHeadersAreDisabledThroughConfiguration(): void
    {
        $client = self::createClient(['environment' => 'ldpdisabled']);
        $this->recreateSchema(self::getResources());

        $client->request('GET', '/ldp_dummies');

        self::assertResponseIsSuccessful();

        $headers = array_change_key_case(self::getHttpResponse()->getHeaders(false));

        self::assertArrayNotHasKey('allow', $headers);
        self::assertArrayNotHasKey('accept-post', $headers);
    }
}
