<?php

namespace Transmogrify;

class ArgsParser
{
    protected $dbHost = 1;

    protected $dbUsername = 2;

    protected $dbPassword = 3;

    protected $dbName = 4;

    protected $dbPrefix = 5;

    protected $ipbAddress = 6;

    protected $apiKey = 7;

    protected $discourseAddress = 8;

    protected $forumIds = 9;

    protected $topicsLimit = 10;

    protected $postsLimit = 11;

    /**
     * ArgsParser constructor.
     *
     * @param int $argc
     *
     * @throws \Exception
     */
    public function __construct($argc)
    {
        if ($argc < ($this->apiKey + 1)) {
            throw new \Exception(
                "Usage:\tphp transmogrify dbHost dbUser dbPassword dbName dbPrefix ipbAddress apiKey [discourseAddress [forumIds [topicsLimit [postsLimit]]]]"
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
            'prefix'   => $argv[$this->dbPrefix],
        ];

        $ipbAddress = rtrim($argv[$this->ipbAddress], '/');

        $apiKey = $argv[$this->apiKey];

        $discourseAddress = isset($argv[$this->discourseAddress])
            ? rtrim($argv[$this->discourseAddress], '/')
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
            $ipbAddress,
            $apiKey,
            $discourseAddress,
            $forumsIds,
            $topicsLimit,
            $postsLimit,
        );
    }
}