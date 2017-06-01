<?php

namespace Elazar\GitLabHud\Command;

use Elazar\GitLabHud\Helper\Auth;
use Gitlab\Client;
use Gitlab\ResultPager;
use GitWrapper\GitWrapper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Show
{
    /**
     * @var GitWrapper
     */
    private $git;

    /**
     * @var SymfonyStyle
     */
    private $style;

    /**
     * @var Auth
     */
    private $auth;

    public function __construct(
        GitWrapper $git,
        SymfonyStyle $style,
        Auth $auth
    ) {
        $this->git = $git;
        $this->style = $style;
        $this->auth = $auth;
    }

    public function __invoke(
        $path,
        InputInterface $input,
        OutputInterface $output
    ) {
        $gitlab = call_user_func($this->auth, $input, $output);
        $requests = $this->getMergeRequests($gitlab, $path);

        if (!$path) {
            $path = getcwd();
        }
        $branches = $this->getLocalBranches($path);

        $open_requests = array_filter($requests, function ($request) use ($branches) {
            return in_array($request['source_branch'], $branches);
        });

        $rows = array_map(
            function ($request) {
                return [
                    $request['source_branch'],
                    $request['web_url'],
                ];
            },
            $open_requests
        );

        $orphaned_branches = array_diff(
            $branches,
            array_map(
                function ($request) {
                    return $request['source_branch'];
                },
                $open_requests
            )
        );

        foreach ($orphaned_branches as $branch) {
            $rows[] = [$branch, '-'];
        }

        $this->style->table(
            ['Branch', 'Merge Request'],
            $rows
        );
    }

    /**
     * @param string $path
     * @return string[]
     */
    private function getLocalBranches($path)
    {
        $output = trim($this->git->git('for-each-ref refs/heads/ --format="%(refname:short)"', $path));
        $list = explode(PHP_EOL, $output);
        return array_diff($list, ['master']);
    }

    /**
     * @param Client $gitlab
     * @param string $path
     * @return array
     */
    private function getMergeRequests(Client $gitlab, $path)
    {
        $pager = new ResultPager($gitlab);
        $fetch = $this->git->workingCopy($path)->getRemote('origin')['fetch'];

        $projects_api = $gitlab->api('projects');
        $projects = $pager->fetchAll($projects_api, 'accessible', [1, 100, 'updated_at', 'desc']);
        $filtered = array_filter($projects, function($project) use ($fetch) {
            return in_array($fetch, [
                $project['ssh_url_to_repo'],
                $project['http_url_to_repo'],
                $project['web_url'],
            ]);
        });
        $project = array_shift($filtered);

        $requests_api = $gitlab->api('merge_requests');
        return $pager->fetchAll($requests_api, 'opened', [$project['id'], 1, 100, 'updated_at', 'desc']);
    }
}
