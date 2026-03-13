# Contributing

Muc tieu cua repository nay la de cong dong co the bo sung nhanh cac template nghiep vu va cai tien he thong ma khong phai sua sau vao core.

## Nguyen tac chung

- Uu tien dong gop theo huong `template-first`
- Template moi phai doc lap, de review, de rollback
- Khong nhung logic nghiep vu dac thu vao core neu co the dua vao `templates/`

## Cau truc template

Template duoc dat tai:

```text
templates/<module>/<template_key>/template.json
```

Module ho tro hien tai:

- `organization`
- `process_mapping`
- `data_architecture`
- `integration`
- `backlog`
- `reports`

## Quy trinh them template moi

1. Tao thu muc moi trong `templates/<module>/<template_key>/`
2. Tao file `template.json`
3. Dat day du metadata:
   - `name`
   - `description`
   - `icon`
   - `version`
   - `author`
   - `tags`
   - `payload`
4. Kiem tra template hien len dung trong UI module tuong ung
5. Cap nhat `templates/README.md` neu template them payload dac thu

## Quy uoc commit

Khuyen nghi dung convention sau:

```text
feat(template): add crm_foundation data architecture template
fix(template): correct procure_to_pay process steps
docs(template): update contribution guide
```

## Pull Request

Khi gui PR:

1. Mo ta ro template dung cho module nao
2. Neu can, kem screenshot UI sau khi apply template
3. Neu template mo rong payload moi, mo ta ro thay doi schema
4. Dam bao khong pha vo template hien co

## Review checklist

- Template hop le ve JSON
- Metadata day du
- Payload dung schema module
- Ten template ro rang
- Khong chen secret, du lieu nhay cam, hoac thong tin noi bo
- Apply template khong lam loi UI/API

## Khi nao can sua core

Chi sua core khi:

- Them module template moi
- Them payload schema moi cho module
- Bo sung renderer/applier dung chung

Neu chi them noi dung template, khong can sua `assets/js/app.js` hoac `api/templates.php`.
