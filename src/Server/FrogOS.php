<?php

namespace FrogOS\OAuth1Example\Client\Server;

use League\OAuth1\Client\Credentials\TokenCredentials;
use League\OAuth1\Client\Server\User;
use League\OAuth1\Client\Signature\SignatureInterface;
use League\OAuth1\Client\Server\Server as Base;

class FrogOS extends Base
{
    protected string $baseUrl;

    public function __construct(
        $clientCredentials,
        string $baseUrl,
        SignatureInterface $signature = null
    ) {
        parent::__construct($clientCredentials, $signature);
        $this->baseUrl = rtrim($baseUrl, "/");
    }

    /**
     * @inheritDoc
     */
    public function urlTemporaryCredentials()
    {
        return sprintf("%s/api/2/oauth1.php/request-token", $this->baseUrl);
    }

    /**
     * @inheritDoc
     */
    public function urlAuthorization()
    {
        return sprintf("%s/app/login", $this->baseUrl);
    }

    /**
     * @inheritDoc
     */
    public function urlTokenCredentials()
    {
        return sprintf("%s/api/2/oauth1.php/access-token", $this->baseUrl);
    }

    /**
     * @inheritDoc
     */
    public function urlUserDetails()
    {
        return sprintf("%s/api/2/?method=auth.whoAmI", $this->baseUrl);
    }

    /**
     * @inheritDoc
     */
    public function userDetails($data, TokenCredentials $tokenCredentials)
    {
        $user = new User();

        $user->uid = $data["data"]["uuid"];
        $user->nickname = $data["data"]["displayname"];
        $user->name = $data["data"]["displayname"];

        $used = [
            "uuid",
            "screen_name",
            "name",
            "location",
            "description",
            "profile_image_url",
            "email",
        ];

        foreach ($data["data"] as $key => $value) {
            if (strpos($key, "url") !== false) {
                if (!in_array($key, $used)) {
                    $used[] = $key;
                }

                $user->urls[$key] = $value;
            }
        }

        $user->extra = array_diff_key($data["data"], array_flip($used));

        return $user;
    }

    /**
     * @inheritDoc
     */
    public function userUid($data, TokenCredentials $tokenCredentials)
    {
        return $data["id"];
    }

    /**
     * @inheritDoc
     */
    public function userEmail($data, TokenCredentials $tokenCredentials)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function userScreenName($data, TokenCredentials $tokenCredentials)
    {
        return $data["name"];
    }
}
