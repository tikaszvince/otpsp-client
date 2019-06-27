<?php

declare(strict_types = 1);

namespace Cheppers\OtpClient\Tests\Unit;

use Cheppers\OtpClient\DataType\InstantOrderStatus;
use Cheppers\OtpClient\OtpSimplePayClient;
use Cheppers\OtpClient\Serializer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Cheppers\OtpClient\OtpSimplePayClient
 */
class OtpSimplePayClientTest extends TestCase
{

    public function casesInstantOrderStatusPost()
    {
        return [
            'basic' => [
                InstantOrderStatus::__set_state([
                    'ORDER_DATE' => 'myOrderDate',
                    'REFNO' => 'myRefNo',
                    'REFNOEXT' => 'myRefNoExt',
                    'ORDER_STATUS' => 'MyOrderStatus',
                    'PAYMETHOD' => 'myPayMethod',
                ]),
                implode(PHP_EOL, [
                    '<?xml version="1.0" encoding="UTF-8"?>',
                    '<Order>',
                    '<ORDER_DATE>myOrderDate</ORDER_DATE>',
                    '<REFNO>myRefNo</REFNO>',
                    '<REFNOEXT>myRefNoExt</REFNOEXT>',
                    '<ORDER_STATUS>MyOrderStatus</ORDER_STATUS>',
                    '<PAYMETHOD>myPayMethod</PAYMETHOD>',
                    '<HASH>myHash</HASH>',
                    '</Order>'
                ]),
                'foo',
            ],
            'not found' => [
                null,
                implode(PHP_EOL, [
                    '<?xml version="1.0" encoding="UTF-8"?>',
                    '<Order>',
                    '<ERROR_CODE>5011</ERROR_CODE>',
                    '<HASH>myHash</HASH>',
                    '<ORDER_STATUS>NOT_FOUND</ORDER_STATUS>',
                    '</Order>'
                ]),
                'foo',
            ],
        ];
    }

    /**
     * @dataProvider casesInstantOrderStatusPost
     */
    public function testInstantOrderStatusPost(?InstantOrderStatus $expected, string $responseBody, string $refNoExt)
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/xml'],
                $responseBody
            ),
            new RequestException('Error Communicating with Server', new Request('GET', 'order/ios.php'))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client([
            'handler' => $handlerStack,
        ]);

        /** @var \Cheppers\OtpClient\Serializer|\PHPUnit\Framework\MockObject\MockObject $serializer */
        $serializer = $this
            ->getMockBuilder(Serializer::class)
            ->getMock();
        $serializer
            ->expects($this->any())
            ->method('encode')
            ->willReturn('myHash');

        $actual = (new OtpSimplePayClient($client, $serializer))
            ->setSecretKey('')
            ->setMerchantId('')
            ->instantOrderStatusPost($refNoExt);

        static::assertEquals($expected, $actual);

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $container[0]['request'];
        static::assertEquals(1, count($container));
        static::assertEquals('POST', $request->getMethod());
        static::assertEquals(['application/x-www-form-urlencoded'], $request->getHeader('Content-type'));
        static::assertEquals(['sandbox.simplepay.hu'], $request->getHeader('Host'));
        static::assertEquals(
            'https://sandbox.simplepay.hu/payment/order/ios.php',
            (string) $request->getUri()
        );
    }

    public function casesInstantOrderStatusPostError()
    {
        return [
            'hash mismatch' => [
                [
                    'class' => \Exception::class,
                    'message' => '@todo',
                    'code' => 1,
                ],
                implode(PHP_EOL, [
                    '<?xml version="1.0" encoding="UTF-8"?>',
                    '<Order>',
                    '<ERROR_CODE>5011</ERROR_CODE>',
                    '<HASH>something else</HASH>',
                    '<ORDER_STATUS>NOT_FOUND</ORDER_STATUS>',
                    '</Order>'
                ]),
                'foo',
            ],
            'unknown' => [
                [
                    'class' => \Exception::class,
                    'message' => 'myOrderStatus',
                    'code' => 42,
                ],
                implode(PHP_EOL, [
                    '<?xml version="1.0" encoding="UTF-8"?>',
                    '<Order>',
                    '<ERROR_CODE>42</ERROR_CODE>',
                    '<HASH>myHash</HASH>',
                    '<ORDER_STATUS>myOrderStatus</ORDER_STATUS>',
                    '</Order>'
                ]),
                'foo',
            ],
        ];
    }

    /**
     * @dataProvider casesInstantOrderStatusPostError
     */
    public function testInstantOrderStatusPostError(array $expected, string $responseBody, string $refNoExt)
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/xml'],
                $responseBody
            ),
            new RequestException('Error Communicating with Server', new Request('GET', 'order/ios.php'))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $client = new Client([
            'handler' => $handlerStack,
        ]);

        /** @var \Cheppers\OtpClient\Serializer|\PHPUnit\Framework\MockObject\MockObject $serializer */
        $serializer = $this
            ->getMockBuilder(Serializer::class)
            ->getMock();
        $serializer
            ->expects($this->any())
            ->method('encode')
            ->willReturn('myHash');

        static::expectException($expected['class']);
        static::expectExceptionMessage($expected['message']);
        static::expectExceptionCode($expected['code']);
        (new OtpSimplePayClient($client, $serializer))
            ->setSecretKey('')
            ->setMerchantId('')
            ->instantOrderStatusPost($refNoExt);
    }
}