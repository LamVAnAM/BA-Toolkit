# Thiết kế nâng cấp module Data Architecture

## Tổng quan kiến trúc

### Mục tiêu thiết kế
1. **Mở rộng database schema** để hỗ trợ quan hệ và thuộc tính nâng cao
2. **Cải thiện frontend** với visualization tương tác cao
3. **Tích hợp sâu** với các module BA khác
4. **Bảo đảm backward compatibility** với dữ liệu hiện có

## Database Schema Design

### Bảng mới: `entity_relationships`
```sql
CREATE TABLE entity_relationships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    department_id INTEGER NOT NULL,
    source_entity_id INTEGER NOT NULL,
    target_entity_id INTEGER NOT NULL,
    relationship_name TEXT,
    relationship_type TEXT CHECK(relationship_type IN ('one-to-one', 'one-to-many', 'many-to-many')),
    cardinality_source TEXT DEFAULT '1',
    cardinality_target TEXT DEFAULT '1',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (source_entity_id) REFERENCES department_entities(id) ON DELETE CASCADE,
    FOREIGN KEY (target_entity_id) REFERENCES department_entities(id) ON DELETE CASCADE
);
```

### Bảng mới: `entity_attributes`
```sql
CREATE TABLE entity_attributes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_id INTEGER NOT NULL,
    attribute_name TEXT NOT NULL,
    data_type TEXT CHECK(data_type IN ('string', 'integer', 'decimal', 'date', 'boolean', 'text')),
    is_primary_key BOOLEAN DEFAULT 0,
    is_foreign_key BOOLEAN DEFAULT 0,
    referenced_entity_id INTEGER,
    referenced_attribute_id INTEGER,
    is_nullable BOOLEAN DEFAULT 1,
    is_unique BOOLEAN DEFAULT 0,
    default_value TEXT,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entity_id) REFERENCES department_entities(id) ON DELETE CASCADE,
    FOREIGN KEY (referenced_entity_id) REFERENCES department_entities(id) ON DELETE SET NULL
);
```

### Bảng mới: `entity_versions`
```sql
CREATE TABLE entity_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_id INTEGER NOT NULL,
    version_number INTEGER NOT NULL,
    snapshot_data TEXT, -- JSON snapshot của entity và attributes
    change_description TEXT,
    changed_by_user_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entity_id) REFERENCES department_entities(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(entity_id, version_number)
);
```

### Cập nhật bảng `department_entities`
Thêm các trường mới:
```sql
ALTER TABLE department_entities ADD COLUMN entity_type TEXT CHECK(entity_type IN ('core', 'reference', 'transaction', 'lookup')) DEFAULT 'core';
ALTER TABLE department_entities ADD COLUMN module_group TEXT;
ALTER TABLE department_entities ADD COLUMN description TEXT;
```

## API Design

### Endpoint mới: `/api/entity_relationships.php`
```php
// CRUD cho quan hệ thực thể
GET  /api/entity_relationships.php?department_id=X&action=load
POST /api/entity_relationships.php?action=save
POST /api/entity_relationships.php?action=delete
```

### Endpoint mới: `/api/entity_attributes.php`
```php
// Quản lý thuộc tính chi tiết
GET  /api/entity_attributes.php?entity_id=X&action=load
POST /api/entity_attributes.php?action=save
POST /api/entity_attributes.php?action=delete
```

### Endpoint mới: `/api/entity_analysis.php`
```php
// Phân tích BA
GET /api/entity_analysis.php?department_id=X&analysis_type=gap
GET /api/entity_analysis.php?department_id=X&analysis_type=impact&entity_id=Y
GET /api/entity_analysis.php?department_id=X&analysis_type=lineage&attribute_id=Z
```

### Cập nhật endpoint hiện có: `/api/manage_data.php`
Thêm support cho các operation mới:
```php
// Thêm type mới
$tableMap['entity_relationships'] = 'entity_relationships';
$tableMap['entity_attributes'] = 'entity_attributes';
```

## Frontend Design

### Component Structure
```
DataArchitectureModule/
├── EntityList/           # Danh sách thực thể với filter/search
├── EntityDetail/         # Chi tiết thực thể với attributes
├── RelationshipBuilder/  # Tool tạo quan hệ drag & drop
├── ERDiagram/           # Sơ đồ ER tương tác
├── AnalysisPanel/       # Panel phân tích BA
└── ExportPanel/         # Panel export tài liệu
```

### ER Diagram Enhancement
Sử dụng **Mermaid với plugin tương tác**:
```javascript
// Enhanced Mermaid configuration
mermaid.initialize({
    startOnLoad: true,
    theme: 'base',
    themeVariables: {
        primaryColor: '#1a73e8',
        primaryTextColor: '#fff',
        primaryBorderColor: '#1a73e8',
        lineColor: '#666',
        secondaryColor: '#34a853',
        tertiaryColor: '#ea4335'
    },
    er: {
        diagramPadding: 20,
        layoutDirection: 'TB'
    }
});

// Interactive features
- Click entity to show details
- Drag entities to reposition
- Hover relationship to highlight
- Context menu for operations
```

### UX Improvements
1. **Drag & Drop Interface**
   - Kéo thả thực thể để tạo quan hệ
   - Kéo để sắp xếp layout
   - Drop zones cho các operation

2. **Search & Filter**
   - Real-time search theo tên, mô tả
   - Filter theo entity type, module
   - Advanced filter với nhiều điều kiện

3. **Bulk Operations**
   - Select multiple entities
   - Batch edit attributes
   - Mass delete với confirmation

4. **Keyboard Shortcuts**
   - Ctrl+N: New entity
   - Ctrl+R: New relationship  
   - Ctrl+S: Save
   - Ctrl+F: Search
   - Esc: Close modal

## Integration Design

### Với Process Mapping
```javascript
// Liên kết entity với process step
function linkEntityToProcess(entityId, processId, stepIndex) {
    // Store mapping in new table: entity_process_mapping
    // Visualize trong cả 2 module
}
```

### Với Integration Module
```javascript
// Map entity với system integration
function mapEntityToSystem(entityId, systemId, dataFlowDirection) {
    // Hiển thị data flow giữa systems
    // Generate interface specifications
}
```

### Với AI Report
Cập nhật `ai_report.php` để include thông tin nâng cao:
```php
$enhancedEntities = $pdo->prepare("
    SELECT e.name, e.entity_type, e.description,
           GROUP_CONCAT(a.attribute_name || ' (' || a.data_type || ')') as attributes,
           GROUP_CONCAT(r.relationship_name) as relationships
    FROM department_entities e
    LEFT JOIN entity_attributes a ON e.id = a.entity_id
    LEFT JOIN entity_relationships r ON e.id = r.source_entity_id
    WHERE e.user_id = ? AND e.department_id = ?
    GROUP BY e.id
");
```

## Migration Strategy

### Phase 1: Database Migration
1. Tạo bảng mới (không xóa bảng cũ)
2. Migration script để convert dữ liệu cũ:
   - Parse comma-separated attributes thành bảng `entity_attributes`
   - Giữ backward compatibility

### Phase 2: API Layer
1. Implement API mới song song với API cũ
2. Update `manage_data.php` để hỗ trợ cả 2 schema
3. Feature flag để toggle giữa old/new

### Phase 3: Frontend Upgrade
1. Build new UI components
2. Progressive enhancement: new features chỉ hiện khi data mới có
3. Fallback to old UI nếu không có data mới

### Phase 4: Deprecation
1. Thông báo cho users về changes
2. Migration tool tự động
3. Sau 1 thời gian, remove old code

## Security Considerations

### Data Access Control
- Tất cả queries phải check `user_id` và `department_id`
- Relationship chỉ được tạo giữa entities cùng department
- Foreign key references phải thuộc cùng user

### Input Validation
- Sanitize tất cả user inputs
- Validate data types và constraints
- Limit relationship cycles (prevent infinite loops)

### Export Security
- Sanitize data trước khi export
- Không export sensitive information
- Access control cho export features

## Performance Considerations

### Database Indexing
```sql
CREATE INDEX idx_entity_relationships_source ON entity_relationships(source_entity_id);
CREATE INDEX idx_entity_relationships_target ON entity_relationships(target_entity_id);
CREATE INDEX idx_entity_attributes_entity ON entity_attributes(entity_id);
CREATE INDEX idx_entity_versions_entity ON entity_versions(entity_id);
```

### Caching Strategy
- Cache ER diagram rendering
- Cache analysis results
- Implement lazy loading cho large datasets

### Frontend Optimization
- Virtual scrolling cho entity list
- Debounce search inputs
- Lazy load analysis components
- Optimize Mermaid rendering

## Testing Strategy

### Unit Tests
- Test database migrations
- Test API endpoints
- Test business logic (relationship validation, etc.)

### Integration Tests
- Test end-to-end workflows
- Test cross-module integration
- Test data consistency

### UI Tests
- Test drag & drop functionality
- Test diagram interactions
- Test responsive design

### Performance Tests
- Test với large datasets (1000+ entities)
- Test rendering performance
- Test export performance