# Yêu cầu nâng cấp module Data Architecture (DA)

## Tổng quan
Module Data Architecture hiện tại cho phép người dùng định nghĩa các thực thể dữ liệu và hiển thị sơ đồ ER cơ bản. Bản review này nhằm xác định các điểm cần cải thiện để nâng cao trải nghiệm người dùng và khả năng phân tích BA.

## Phân tích hiện trạng

### Tính năng hiện có
1. **Thêm thực thể**: Cho phép nhập tên thực thể và thuộc tính (dạng chuỗi phân cách bằng dấu phẩy)
2. **Hiển thị danh sách**: Hiển thị thực thể trong bảng
3. **Sơ đồ ER**: Tự động tạo sơ đồ ER cơ bản bằng Mermaid
4. **CRUD cơ bản**: Thêm, xóa thực thể
5. **Tích hợp báo cáo**: Dữ liệu DA được đưa vào báo cáo AI

### Hạn chế hiện tại
- Không hỗ trợ định nghĩa quan hệ giữa các thực thể
- Thuộc tính chỉ là chuỗi văn bản, không có kiểu dữ liệu
- Không có khóa chính/khóa ngoại
- Sơ đồ ER đơn giản, không hiển thị quan hệ
- Không có version control cho thay đổi kiến trúc
- Thiếu tính năng phân tích dữ liệu BA
- UX cơ bản, thiếu tính tương tác cao

## Yêu cầu cải tiến

### 1. Quản lý quan hệ thực thể
**Mô tả**: Người dùng cần định nghĩa quan hệ giữa các thực thể với cardinality (1:1, 1:N, N:M)

**Tiêu chí chấp nhận**:
- [ ] Người dùng có thể tạo quan hệ giữa 2 thực thể
- [ ] Hỗ trợ các loại cardinality: one-to-one, one-to-many, many-to-many
- [ ] Có thể đặt tên cho quan hệ
- [ ] Quan hệ được hiển thị rõ ràng trong sơ đồ ER
- [ ] Có thể chỉnh sửa/xóa quan hệ

### 2. Định nghĩa thuộc tính nâng cao
**Mô tả**: Thuộc tính cần hỗ trợ kiểu dữ liệu, ràng buộc, và vai trò (PK/FK)

**Tiêu chí chấp nhận**:
- [ ] Hỗ trợ các kiểu dữ liệu: string, integer, decimal, date, boolean, text
- [ ] Có thể đánh dấu thuộc tính là khóa chính (PK)
- [ ] Có thể đánh dấu thuộc tính là khóa ngoại (FK) tham chiếu đến thực thể khác
- [ ] Hỗ trợ ràng buộc: NOT NULL, UNIQUE, DEFAULT value
- [ ] Có thể thêm mô tả cho từng thuộc tính
- [ ] Hiển thị kiểu dữ liệu trong bảng danh sách

### 3. Sơ đồ ER nâng cao
**Mô tả**: Cải thiện visualization với nhiều tùy chọn hiển thị

**Tiêu chí chấp nhận**:
- [ ] Hiển thị quan hệ với cardinality trên sơ đồ
- [ ] Màu sắc khác nhau cho các loại thực thể (core, reference, transaction)
- [ ] Có thể nhóm thực thể theo module/phân hệ
- [ ] Zoom in/out và pan sơ đồ
- [ ] Export sơ đồ dưới dạng PNG/SVG
- [ ] Tùy chọn layout: left-to-right, top-to-bottom
- [ ] Hiển thị tooltip với thông tin chi tiết khi hover

### 4. Mapping luồng dữ liệu
**Mô tả**: Kết nối thực thể với quy trình và tích hợp hệ thống

**Tiêu chí chấp nhận**:
- [ ] Có thể liên kết thực thể với quy trình (process) trong module Process Mapping
- [ ] Hiển thị luồng dữ liệu giữa các thực thể trong quy trình
- [ ] Liên kết thực thể với hệ thống trong module Integration
- [ ] Visualize data flow giữa các hệ thống
- [ ] Phân tích impact khi thay đổi thực thể

### 5. Phân tích BA
**Mô tả**: Thêm tính năng phân tích phục vụ Business Analysis

**Tiêu chí chấp nhận**:
- [ ] Gap analysis: so sánh AS-IS vs TO-BE data architecture
- [ ] Impact analysis: phân tích ảnh hưởng khi thay đổi thực thể
- [ ] Data lineage: truy vết nguồn gốc và luồng dữ liệu
- [ ] Data quality metrics: đánh giá chất lượng dữ liệu
- [ ] Consistency check: kiểm tra tính nhất quán giữa các thực thể
- [ ] Generate data dictionary tự động

### 6. Version control & History
**Mô tả**: Theo dõi lịch sử thay đổi kiến trúc dữ liệu

**Tiêu chí chấp nhận**:
- [ ] Lưu version mỗi khi có thay đổi quan trọng
- [ ] Xem lịch sử thay đổi theo thời gian
- [ ] So sánh sự khác biệt giữa các version
- [ ] Rollback về version trước đó
- [ ] Ghi chú/thẻ cho từng version
- [ ] Hiển thị ai thay đổi và khi nào

### 7. Cải thiện UX/UI
**Mô tả**: Nâng cao trải nghiệm người dùng

**Tiêu chí chấp nhận**:
- [ ] Drag & drop để tạo quan hệ giữa các thực thể
- [ ] Search và filter thực thể theo nhiều tiêu chí
- [ ] Bulk operations: thêm/xóa nhiều thực thể cùng lúc
- [ ] Keyboard shortcuts cho thao tác thường dùng
- [ ] Responsive design cho mobile/tablet
- [ ] Dark/light mode
- [ ] Tour/hướng dẫn cho người dùng mới

### 8. Export & Documentation
**Mô tả**: Xuất tài liệu kiến trúc dữ liệu

**Tiêu chí chấp nhận**:
- [ ] Export data dictionary dưới dạng Excel/CSV
- [ ] Generate schema documentation (HTML/PDF)
- [ ] Export ER diagram với chất lượng cao
- [ ] Generate SQL DDL scripts
- [ ] Export to OpenAPI/Swagger cho API documentation
- [ ] Integration với báo cáo AI hiện có

### 9. Integration với module khác
**Mô tả**: Tích hợp chặt chẽ hơn với các module BA khác

**Tiêu chí chấp nhận**:
- [ ] Tự động đề xuất thực thể từ survey data
- [ ] Liên kết thực thể với requirements trong backlog
- [ ] Hiển thị data requirements trong báo cáo tổng hợp
- [ ] Cross-module search: tìm kiếm xuyên suốt các module
- [ ] Unified reporting: báo cáo tích hợp tất cả thông tin BA

## Ưu tiên triển khai

### Phase 1 (High Priority)
1. Quản lý quan hệ thực thể
2. Định nghĩa thuộc tính nâng cao
3. Sơ đồ ER nâng cao

### Phase 2 (Medium Priority)
4. Cải thiện UX/UI
5. Export & Documentation
6. Version control

### Phase 3 (Low Priority)
7. Phân tích BA
8. Mapping luồng dữ liệu
9. Integration với module khác

## Technical Considerations
- Database schema cần mở rộng để hỗ trợ quan hệ và kiểu dữ liệu
- Frontend cần upgrade Mermaid và thêm interactive features
- API cần hỗ trợ các operation mới
- Cần maintain backward compatibility với dữ liệu hiện có