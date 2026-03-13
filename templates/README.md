# Templates

Thu muc nay chua cac template dong gop cho tung module.

Quy uoc:

- Mỗi template nam trong `templates/<module>/<template_key>/template.json`
- `module` hop le: `organization`, `process_mapping`, `data_architecture`, `integration`, `backlog`, `reports`
- `template_key` chi dung `a-z`, `0-9`, `_`, `-`

Mot file `template.json` toi thieu can co:

```json
{
  "name": "Template Name",
  "description": "Mo ta ngan",
  "icon": "fa-layer-group",
  "version": "1.0.0",
  "author": "Your Name",
  "tags": ["domain"],
  "payload": {}
}
```

Payload tuy module:

- `organization`: `{ "departments": [{ "name": "...", "sponsor": "..." }] }`
- `process_mapping`: `{ "items": [{ "name": "...", "type": "AS-IS|TO-BE", "steps": "..." }] }`
- `data_architecture`: `{ "entities": [...], "relationships": [...] }`
- `integration`: `{ "items": [{ "system_name": "...", "interface_type": "..." }] }`
- `backlog`: `{ "items": [{ "requirement": "...", "priority": "High|Medium|Low", "status": "New|Analyze|Done" }] }`
- `reports`: tu do, phuc vu mo rong sau
