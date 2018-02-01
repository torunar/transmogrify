<?php

namespace Transmogrify;

class ArgsParser
{
    protected $dbHost = 1;

    protected $dbUsername = 2;

    protected $dbPassword = 3;

    protected $dbName = 4;

    protected $apiKey = 5;

    protected $discourseAddress = 6;

    protected $forumIds = 7;

    protected $topicsLimit = 8;

    protected $postsLimit = 9;

    /**
     * ArgsParser constructor.
     *
     * @param $argc
     *
     * @throws \Exception
     */
    public function __construct($argc)
    {
        if ($argc < ($this->apiKey + 1)) {
            throw new \Exception(
                "Usage:\tphp transmogrify.php dbHost dbUser dbPassword dbName apiKey [discourseAddress] [forumIds] [topicsLimit] [postsLimit]"
            );
        }
    }

    /**
     * Parses CLI args.
     *
     * @param array $argv
     *
     * @return array
     */
    public function parse(array $argv)
    {
        $dbSettings = [
            'host'     => $argv[$this->dbHost],
            'username' => $argv[$this->dbUsername],
            'password' => $argv[$this->dbPassword],
            'name'     => $argv[$this->dbName],
        ];

        $apiKey = $argv[5];

        $discourseAddress = isset($argv[$this->discourseAddress])
            ? $argv[$this->discourseAddress]
            : 'http://localhost:4000';
        $forumsIds = isset($argv[$this->forumIds])
            ? explode(',', $argv[$this->forumIds])
            : [];
        $topicsLimit = isset($argv[$this->topicsLimit])
            ? (int) $argv[$this->topicsLimit]
            : null;
        $postsLimit = isset($argv[$this->postsLimit])
            ? (int) $argv[$this->postsLimit]
            : null;

        return array(
            $dbSettings,
            $apiKey,
            $discourseAddress,
            $forumsIds,
            $topicsLimit,
            $postsLimit,
        );
    }
}