# Variance project documentation

Public source documentation describes architecture and reusable application
procedures. The private unil-ops workspace owns infrastructure inventory,
operational procedures, release evidence, backup locations and the sole task backlog.

## Public entry points

- [Architecture](architecture.md)
- [Application workflow](workflow.md)
- [Queues and scheduled jobs](queues_jobs.md)
- [API reference](api_endpoints.md)
- [Deployment guidance](deployment_notes.md)
- [Dependency maintenance](dependency_updates.md)
- [PHP runtime boundary](php259-runtime.md)
- [Laravel code map](laravel_current_code_map.md)
- [Laravel walkthrough](laravel_process_walkthrough.md)
- [Facsimiles](facsimiles.md)
- [Chapter import](chapters_import_flow.md)
- [Historical editor requirements](editor_backlog.md)

## Private operator documents

The following material remains at its existing paths in the operator workspace
and is explicitly excluded from public Git:

- Dated cutover history and handoff scripts, release/execution notes and checklists.
- The annotated historical `product_backlog.md`; current work belongs only to Operations.
- `evidence/`, including test receipts, operational captures and screenshots.
- Internal pagination/comparison acceptance notes and presentation material.
- The top-level `livrables/` directory of editorial/design deliverables.
- `internal/`, including the private preservation index `README.local-documents.md`.

The `.gitignore` entries name the reviewed paths rather than excluding this
whole documentation directory. Two previously tracked operational archives are
no longer tracked; their local files are retained unchanged. Earlier public
revisions still exist in Git history. The private preservation index records
original hashes and the verified recovery archive.

A public clone does not contain these private documents, ignored application data
or recovery payloads. Restore the private workspace through its documented backup
procedure before treating it as a complete operational workspace. A clean Git
status is not proof of a complete backup or a tested deployment.

Before VM or deployment work, load the project context and state the target,
runtime and rollback boundary. Historical instructions do not authorize new
migrations, restores, cleanup or deployments.
