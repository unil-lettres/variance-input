# Dependency security refresh — 30 September 2026

Tracked in Operations #108, extending the existing Medite dependency audit to the repository dependency cleanup requested by Julien. Baseline: `91e64c2698ef5bc681ddba300a335a69841567a4`.

## Scope

GitHub reported 62 open Dependabot alerts: 38 in Poetry, 17 in Composer and seven in npm (three critical, 36 high, 20 medium, three low). The candidate refreshes the affected lockfiles, preserving Laravel 13, PHP 8.3, Node 22 and Python 3.12. Guzzle remains on major 7. No application source, database migration or deployment configuration changes.

Medite constraints move to the patched lines for setuptools (83), lxml (6), Black (26) and pytest (9), and require NLTK >=3.10.3. Lock refresh also updates soupsieve. An independent pip-audit found a Click advisory beyond the initial GitHub list; Click is updated too. Changes in Python dependency major versions require the Medite suite, native extension build and image build before release.

## Old Dependabot PRs

- #11 targeted the removed `medite/app/install_files` manifests: closed.
- #14 proposed Vite 6.4.1; the baseline already has 6.4.2: closed.
- #15 proposed CommonMark 2.7.1 and related old transitive versions; the baseline already superseded those proposals and this refresh goes further: closed.
- #16 updates Black, pytest and setuptools but leaves other current vulnerabilities; this candidate includes those fixes and the broader current audit. Replace it only once this candidate is published and its validation is reviewed.

Closing obsolete Dependabot PRs is an activity that resumes paused updates; merging outdated changes is unnecessary. See [GitHub’s pause/resume rules](https://docs.github.com/en/code-security/reference/supply-chain-security/troubleshoot-dependabot/dependabot-updates-stopped). Existing monthly configuration remains unchanged.

## Residual alert

[GHSA-8mgp-746c-j5xp](https://github.com/advisories/GHSA-8mgp-746c-j5xp) affects NLTK through 3.10.3 and has no published patched version at review time. It concerns caller-controlled paths in model persistence APIs (`TransitionParser`, `AveragedPerceptron`, `PerceptronTagger.save_to_json`, `save_maxent_params`). No use of those APIs or pathsec was found in the examined Medite Python sources. Current sentence tokenization loads a fixed French resource. This is a bounded source review, not a universal exploitability guarantee; the alert is not dismissed. Track the upstream fix under #108.

## Validation and release boundary

Composer audit: zero advisories. npm audit: zero vulnerabilities. Laravel: 163 tests / 886 assertions pass; Vite build passes. French sentence-tokenization smoke with NLTK 3.10.3 passes. The first Laravel run mounted only its subdirectory and failed legacy rendering tests due to missing sibling sources; the complete repository mount passes.

Full Medite suite and image builds are recorded separately when complete. No production/staging deployment accompanies this preparation. GitHub’s default-branch alert count will change only after the corrected locks reach that branch and are rescanned; running servers remain on their existing dependencies until separately deployed and accepted.
