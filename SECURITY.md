# Security Policy

## Reporting a vulnerability

Do **not** open a public issue for security reports.

Use GitHub's private **Security Advisories** ("Report a vulnerability" on the
Security tab) to report vulnerabilities privately. You will receive a
response within 5 business days.

## Scope

This package is a client library. It does **not** connect to live SGK/MEDULA
services, does not process real patient data, and ships no credentials.

Please keep it that way in reports too: never attach real patient data
(TC kimlik no, takip no, basvuru no of real persons) to any issue or advisory.
Use clearly synthetic values, as the test suite does.

## Supported versions

| Version | Supported |
|---------|-----------|
| 0.x     | ✅        |
