<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Tests\Support\Setup;

use GuzzleHttp\Exception\GuzzleException;
use SolveBeam\Twinfield\Connect\Registration;
use SolveBeam\Twinfield\Connect\RegistrationException;
use SolveBeam\Twinfield\OAuth2\UnexpectedResponseException;
use SolveBeam\Twinfield\Office\OfficeSummary;
use SolveBeam\Twinfield\Tests\Support\Config;
use SolveBeam\Twinfield\Tests\Support\Environment;
use SolveBeam\Twinfield\Tests\Support\Session;

/**
 * Guided first-run setup for examples, tests and dev scripts:
 *
 * 1. Asks for client ID, client secret and redirect URI when missing, or registers with Twinfield
 *    Connect when you have no Twinfield client of your own.
 * 2. Starts the browser login when there is no refresh token yet.
 * 3. Asks for the default company (office) when missing.
 *
 * Values are saved in ~/.config/twinfield/.env. Without an interactive
 * terminal nothing is asked; a SetupException explains what to do instead.
 */
final readonly class Setup
{
    private const string DEFAULT_REDIRECT_URI = 'http://localhost:8765/callback';

    /** Offices shown when choosing the default company. */
    private const int OFFICES_SHOWN = 15;

    public function __construct(
        private Prompt $prompt,
    ) {
    }

    /**
     * Completes whatever is missing and returns the updated configuration.
     *
     * @param bool $all Ask every question, also when a value exists (`composer twinfield:setup`).
     */
    public function ensure(Config $config, bool $all = false): Config
    {
        if ($all) {
            $this->requireInteractive('`composer twinfield:setup` needs an interactive terminal.');
        }

        if ($all || !$config->hasCredentials()) {
            $config = $this->credentials($config);
        }

        if ($all || !(new Session($config))->hasRefreshToken()) {
            $this->requireInteractive(
                'No Twinfield login yet. Run `composer twinfield:login` in a terminal first.',
            );

            (new LoopbackLogin($config, $this->prompt))->run();
        }

        if ($all || $config->get('TWINFIELD_COMPANY') === null) {
            $config = $this->company($config);
        }

        return $config;
    }

    private function credentials(Config $config): Config
    {
        $envFile = new EnvFile($config->configDir . '/.env');

        $this->requireInteractive(sprintf(
            "Twinfield credentials are missing.\n"
            . "Run `composer twinfield:setup` in a terminal (it can also log in without a client of your own),\n"
            . "or add these to %s:\n"
            . "  TWINFIELD_CLIENT_ID=…\n  TWINFIELD_CLIENT_SECRET=…\n  TWINFIELD_REDIRECT_URI=%s",
            $envFile->path,
            self::DEFAULT_REDIRECT_URI,
        ));

        $this->prompt->line();
        $this->prompt->line('Twinfield setup');
        $this->prompt->line('===============');
        $this->prompt->line('Log in with your own OAuth client from the Twinfield developer portal');
        $this->prompt->line('(https://developers.twinfield.com), or without one through Twinfield Connect');
        $this->prompt->line('(' . $config->connectUrl() . '), which uses the Twinfield client of SolveBeam.');
        $this->prompt->line('Values are saved in ' . $envFile->path . ' (only readable by you).');
        $this->prompt->line();

        $default = $config->get('TWINFIELD_CLIENT_ID') !== null ? 'yes' : 'no';

        while (true) {
            $answer = strtolower($this->prompt->ask('Do you have your own Twinfield client ID? (yes/no)', $default));

            if (in_array($answer, ['yes', 'y', 'no', 'n'], true)) {
                break;
            }

            $this->prompt->line('  Answer yes or no.');
        }

        if ($answer[0] === 'n') {
            return $this->connect($config, $envFile);
        }

        $clientId = $this->prompt->ask('Client ID', $config->get('TWINFIELD_CLIENT_ID'));
        $clientSecret = $this->prompt->askSecret(
            'Client secret',
            keepCurrent: $config->get('TWINFIELD_CLIENT_SECRET') !== null,
        );

        $this->prompt->line();
        $this->prompt->line('The redirect URI must be registered for this client exactly as entered here.');
        $this->prompt->line('The login listens on this address to receive the authorization code.');
        $redirectUri = $this->prompt->ask(
            'Redirect URI',
            $config->get('TWINFIELD_REDIRECT_URI') ?? self::DEFAULT_REDIRECT_URI,
        );

        $values = ['TWINFIELD_CLIENT_ID' => $clientId, 'TWINFIELD_REDIRECT_URI' => $redirectUri];

        if ($clientSecret !== '') {
            $values['TWINFIELD_CLIENT_SECRET'] = $clientSecret;
        }

        $envFile->set($values);

        $this->prompt->line();
        $this->prompt->line('Saved ' . $envFile->path);
        $this->prompt->line();

        return Config::load();
    }

    /**
     * Registers this checkout with Twinfield Connect, unless it already is registered for the
     * redirect URI.
     */
    private function connect(Config $config, EnvFile $envFile): Config
    {
        $this->prompt->line();
        $this->prompt->line('The login listens on this address to receive the authorization code.');
        $redirectUri = $this->prompt->ask(
            'Redirect URI',
            $config->get('TWINFIELD_REDIRECT_URI') ?? self::DEFAULT_REDIRECT_URI,
        );

        // Your own client wins when both are set, so clear it.
        $values = [
            'TWINFIELD_CLIENT_ID' => '',
            'TWINFIELD_CLIENT_SECRET' => '',
            'TWINFIELD_REDIRECT_URI' => $redirectUri,
        ];

        $registered = $config->get('TWINFIELD_CONNECT_CLIENT_ID') !== null
            && $config->get('TWINFIELD_CONNECT_CLIENT_SECRET') !== null
            && $config->get('TWINFIELD_CONNECT_REDIRECT_URI') === $redirectUri;

        if (!$registered) {
            $this->prompt->line('Registering with Twinfield Connect…');

            try {
                $client = (new Registration(connectUrl: $config->connectUrl()))->register(
                    'Twinfield PHP SDK on ' . (gethostname() ?: 'a development machine'),
                    [$redirectUri],
                );
            } catch (RegistrationException | UnexpectedResponseException | GuzzleException $exception) {
                throw new SetupException(
                    'Registering with Twinfield Connect failed: ' . $exception->getMessage(),
                    0,
                    $exception,
                );
            }

            $values['TWINFIELD_CONNECT_CLIENT_ID'] = $client->clientId;
            $values['TWINFIELD_CONNECT_CLIENT_SECRET'] = $client->clientSecret;
            // A registration is for its redirect URIs: register again when it changes.
            $values['TWINFIELD_CONNECT_REDIRECT_URI'] = $redirectUri;
        }

        $envFile->set($values);

        $this->prompt->line();
        $this->prompt->line('Saved ' . $envFile->path);
        $this->prompt->line();

        return Config::load();
    }

    private function company(Config $config): Config
    {
        $this->requireInteractive(sprintf(
            'No default company. Add TWINFIELD_COMPANY=… to %s/.env.',
            $config->configDir,
        ));

        $offices = (new Environment($config, new Session($config)))->client()->offices()->list();

        if ($offices === []) {
            throw new SetupException('This Twinfield user has no access to any office.');
        }

        $this->prompt->line(sprintf('This login has access to %d office(s):', count($offices)));

        foreach (array_slice($offices, 0, self::OFFICES_SHOWN) as $office) {
            $this->prompt->line(sprintf('  %-12s %s', $office->code, $office->name));
        }

        if (count($offices) > self::OFFICES_SHOWN) {
            $this->prompt->line(sprintf('  … and %d more', count($offices) - self::OFFICES_SHOWN));
        }

        $codes = array_map(static fn (OfficeSummary $office): string => $office->code, $offices);
        $default = $config->get('TWINFIELD_COMPANY') ?? $codes[0];

        while (true) {
            $code = $this->prompt->ask('Default company code for examples and tests', $default);

            if (in_array($code, $codes, true)) {
                break;
            }

            $this->prompt->line('  Unknown company code for this login.');
        }

        (new EnvFile($config->configDir . '/.env'))->set(['TWINFIELD_COMPANY' => $code]);

        $this->prompt->line('Saved TWINFIELD_COMPANY=' . $code);
        $this->prompt->line();

        return Config::load();
    }

    private function requireInteractive(string $message): void
    {
        if (!$this->prompt->isInteractive()) {
            throw new SetupException($message);
        }
    }
}
