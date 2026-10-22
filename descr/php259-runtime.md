# Variance: PHP runtime reconciliation

Operations #259 records deployment and acceptance. This branch reconciles the
application and construction sources; it does not deploy them. The PHP 8.3
Dockerfile uses the verified 8.3.36 official manifest digest and asserts the
version. The separately retained runtime-overlay.Dockerfile documents the
source-verified binary overlay used in the attended maintenance. A fresh source
build has a different image identity and must be qualified before deployment.

Staging/production Compose requires explicit reviewed PHP image references.
Set the variables in the operator environment or a private Compose env file;
never use an old image merely to satisfy interpolation. Validate the full
environment-specific Compose/override set before any write. Existing host
overlays, generated configuration, code/cache volumes and external media must
be reconciled against the operations runbook. Do not remove volumes, migrate
SQL or restart a VM as part of Git reconciliation. Never copy host credentials
or raw operational diagnostics into this public repository.

The emergency PHP 8.3 overlay takes a reviewed pre-change application image as
BASE and the official php-8.3.36.tar.xz as its build context. Verify source SHA256
dd54693aa334c5c4d72e9aee96b082c2473750a18a6c1d105789c93a3b1692fd
before building. Exact environment image identities and retained recovery
belong in the private operations evidence.

The development candidate includes staged 44da065 (dependency updates and
private export group-read fix), plus the already deployed pagination/reader
source overlays. Production still has its earlier application release: do not
promote these application changes incidentally during a PHP-only operation.
New exports use the storage group and mode 0640. Existing archives require
individual review; do not recursively chmod storage or delete them by age.

The integrated reader remains PHP 7.4.33 with upstream minimal mitigation
712e60f9edfd03f0960fc4fc3c2c375f42b90aa2, not restored upstream support. Its
Dockerfile verifies the original source and patch, verifies all 17 patched files
and rebuilds PHP/core extensions. Composer autostart stays disabled: install
application dependencies explicitly in a qualified build, never at a PHP restart.
Standalone legacy deployments are outside this release boundary.
