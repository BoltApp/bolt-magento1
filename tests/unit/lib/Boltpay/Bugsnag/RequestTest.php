<?php
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    /**
     * @test
     * @dataProvider isRequestCases
     */
    public function isRequest($data)
    {
        if (!empty($data['REQUEST_METHOD'])) {
            $_SERVER['REQUEST_METHOD'] = $data['REQUEST_METHOD'];
        } else {
            unset($_SERVER['REQUEST_METHOD']);
        }
        $this->assertEquals($data['expect'], Bugsnag_Request::isRequest());
    }

    public function isRequestCases()
    {
        return [
            [
                'data' => [
                    'REQUEST_METHOD' => null,
                    'expect' => false
                ]
            ],
            [
                'data' => [
                    'REQUEST_METHOD' => 'GET',
                    'expect' => true
                ]
            ],
            [
                'data' => [
                    'REQUEST_METHOD' => 'PUT',
                    'expect' => true
                ]
            ],
            [
                'data' => [
                    'REQUEST_METHOD' => 'POST',
                    'expect' => true
                ]
            ],
            [
                'data' => [
                    'REQUEST_METHOD' => 'DELETE',
                    'expect' => true
                ]
            ],
            [
                'data' => [
                    'REQUEST_METHOD' => null,
                    'expect' => false
                ]
            ],
        ];
    }

    /**
     * @test
     * @dataProvider getRequestMetaDataCases
     */
    public function getRequestMetaData($data)
    {
        foreach ($data as $key => $value) {
            if ($key !== 'expect') {
                if (!empty($value)) {
                    $_SERVER[$key] = $value;
                } else {
                    $_SERVER[$key] = '';
                }
            }
        }
        $this->assertEquals($data['expect'], Bugsnag_Request::getRequestMetaData());
    }

    /**
     * @return array
     */
    public function getRequestMetaDataCases()
    {
        return [
            [
                'data' => [
                    'REQUEST_METHOD' => '',
                    'CONTENT_TYPE' => '',
                    'HTTP_USER_AGENT' => '',
                    'REQUEST_URI' => '',
                    'HTTPS' => '',
                    'SERVER_PORT' => '',
                    'HTTP_X_FORWARDED_FOR' => '',
                    'REMOTE_ADDR' => '',
                    'HTTP_HOST' => '',
                    'expect' => [
                        'request' => [
                            'url' => 'http://',
                            'httpMethod' => '',
                            'clientIp' => '',
                            'userAgent' => '',
                            'headers' => [
                                'User-Agent' => '',
                                'X-Forwarded-For' => '',
                                'Host' => ''
                            ]
                        ]
                    ]
                ]
            ],
        ];
    }
}
