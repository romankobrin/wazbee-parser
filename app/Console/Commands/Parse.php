<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

class Parse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:parse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    private CookieJar $jar;
    private Client $client;
    private ?string $xsrfToken;
    private ?string $csrfToken;

    public function __construct()
    {
        parent::__construct();
        $this->jar = new CookieJar();
        $this->client = new Client(['cookies' => true]);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Step 1. Open login page for getting cookies, xsrf an csrf tokens
        $this->getPage('https://aff.pledoo.partners/en/login');

        // Step 2. Authorization
        $this->postLogin(env('PARTNER_USER'), env('PARTNER_PASS'));

        // Step 3. Open page with stats
        $this->getPage('https://aff.pledoo.partners/en/reports/Revenue');
    }

    protected function getPage(string $url): string
    {
        $response = $this->client->request('GET', $url, ['cookies' => $this->jar]);
        $this->setXsrfToken();
        $this->setCsrfToken((string)$response->getBody());

        return (string)$response->getBody();
    }

    protected function setXsrfToken(): void
    {
        if ($xsrfToken = $this->jar->getCookieByName('XSRF-TOKEN')) {
            $this->xsrfToken = $xsrfToken->getValue();
        }
    }
    protected function setCsrfToken(string $html): void
    {
        $loginPage = new Crawler($html);
        $this->csrfToken = $loginPage->filter('meta[name="csrf-token"]')->attr('content');
    }

    protected function postLogin(string $login, string $password): ?ResponseInterface
    {
        $response = $this->client->post('https://aff.pledoo.partners/en/login', [
            'json' => ["login" => $login,"password" => $password],
            'headers' => [
                'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'x-requested-with' => 'XMLHttpRequest',
                'x-xsrf-token' => $this->xsrfToken,
                'x-csrf-token' => $this->csrfToken
            ],
            'cookies' => $this->jar
        ]);

        return $response;
    }
}
