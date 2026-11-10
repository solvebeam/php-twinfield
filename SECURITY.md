# Security Policy

## Supported versions

Security fixes go into the latest release. Older releases don't get fixes, so update to the latest version first.

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Report it privately through [GitHub security advisories](https://github.com/solvebeam/twinfield-php/security/advisories/new) ("Report a vulnerability" on the Security tab of the repository). You can expect a first response within 7 days.

A useful report has:

- the version of the SDK and of PHP;
- what goes wrong and what an attacker can do with it;
- the steps or a small script to reproduce it.

Leave real credentials and accounting data out of the report: no client secrets, access or refresh tokens, and no request or response XML/JSON of a live company. Replace them with made-up values.

After a fix is released, the advisory is published and you are credited, unless you prefer not to be.

## Scope

This policy covers the code of this SDK, for example:

- leaking client secrets, access tokens or refresh tokens (in logs, exceptions or the cache);
- XML or JSON handling that lets input change a request in a way the caller didn't intend;
- problems in the OAuth 2.0 flow as the SDK implements it.

Not covered here:

- vulnerabilities in Twinfield itself (the login, the web services, the REST API): report those to Wolters Kluwer, the company behind Twinfield;
- vulnerabilities in a dependency: report those to that project. If the SDK needs a change to stay safe, an issue or pull request here is welcome.
