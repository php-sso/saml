---
title: "Develop"
weight: 5
lead: "Run the checks."
---

```
composer install
composer check     # phpstan, then phpunit
```

The tests build real signed answers with a fake identity provider (`tests/support/IdpFixture.php`) and run `SignIn` against
in-memory implementations of both interfaces.
