<?php

namespace Elazar\GitLabHud\Helper;

use Gitlab\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Auth
{
    /**
     * @var SymfonyStyle
     */
    private $style;

    public function __construct(
        SymfonyStyle $style
    ) {
        $this->style = $style;
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output
    ) {
        $auth_path = getenv('HOME') . '/.gitlab-hud-auth';

        if (file_exists($auth_path)) {
            $auth = json_decode(file_get_contents($auth_path), true);
            $base_url = $auth['base_url'] . '/api/v4/';
            $token = $auth['token'];
            return $this->getClient($base_url, $token);
        }

        $this->style->note('No personal access token found for GitLab');
        $this->style->comment('For more information, see https://goo.gl/ilYtJe');

        $token = $this->style->askHidden('Personal access token');
        if (empty($token)) {
            $this->style->error('Token must be non-empty');
            return 1;
        }

        $base_url = $this->style->ask('Base URL of Gitlab install');
        if (empty($base_url)) {
            $this->style->error('Base URL must be non-empty');
            return 1;
        }

        if (substr($base_url, -1) == '/') {
            $base_url = rtrim($base_url, '/');
        }

        $auth = [
            'base_url' => $base_url,
            'token' => $token,
        ];

        file_put_contents($auth_path, json_encode($auth));

        return $this->getClient($base_url, $token);
    }

    /**
     * @param string $base_url
     * @param string $token
     * @return Client
     */
    private function getClient($base_url, $token)
    {
        $client = new Client($base_url);
        $client->authenticate($token, Client::AUTH_HTTP_TOKEN);
        return $client;
    }
}
