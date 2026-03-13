# Contributing

The goal of this repository is to let contributors add business templates quickly and improve the system without making unnecessary changes to the core codebase.

## General Principles

- Prefer a `template-first` contribution model
- New templates should be isolated, easy to review, and easy to roll back
- Avoid embedding domain-specific business logic into the core when it can live under `templates/`

## Template Structure

Templates are stored at:

```text
templates/<module>/<template_key>/template.json
```

Currently supported modules:

- `organization`
- `process_mapping`
- `data_architecture`
- `integration`
- `backlog`
- `reports`

## How to Add a New Template

1. Create a new directory under `templates/<module>/<template_key>/`
2. Add a `template.json` file
3. Include complete metadata:
   - `name`
   - `description`
   - `icon`
   - `version`
   - `author`
   - `tags`
   - `payload`
4. Verify that the template renders correctly in the corresponding module UI
5. Update `templates/README.md` if the template introduces a module-specific payload pattern

## Commit Convention

Recommended commit message examples:

```text
feat(template): add crm_foundation data architecture template
fix(template): correct procure_to_pay process steps
docs(template): update contribution guide
```

## Pull Requests

When opening a PR:

1. Clearly describe which module the template is for
2. Include screenshots if the UI output matters
3. If the template extends payload structure, explain the schema change clearly
4. Make sure the change does not break existing templates

## Review Checklist

- The template is valid JSON
- Metadata is complete
- The payload matches the target module schema
- The template name is clear and specific
- No secrets, sensitive data, or internal-only information are included
- Applying the template does not break UI or API behavior

## When Core Changes Are Appropriate

Core code changes are justified only when:

- A new template-capable module is introduced
- A new shared payload schema is required
- A shared renderer or template applier must be extended

If you are only adding template content, you should not need to modify `assets/js/app.js` or `api/templates.php`.
