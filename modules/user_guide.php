<div class="module-container">
    <div class="section-header">
        <h1><i class="fas fa-circle-info"></i> Hướng dẫn sử dụng / User Guide</h1>
    </div>

    <div class="section" style="max-width: 1100px; margin: 20px auto;">
        <div class="form-grid">
            <div class="form-group full">
                <div class="da-welcome-card">
                    <h3>Bắt đầu nhanh</h3>
                    <p>BA Toolkit hỗ trợ khảo sát yêu cầu, chuẩn hóa nội dung bằng AI, dựng sơ đồ, quản lý kiến trúc dữ liệu và tạo báo cáo tổng hợp cho từng đơn vị/phòng ban.</p>
                </div>
            </div>

            <div class="form-group full">
                <h3 style="margin-bottom: 12px;">Quy trình đề xuất</h3>
                <div class="da-template-grid">
                    <div class="da-template-card">
                        <i class="fas fa-building"></i>
                        <div class="name">1. Tạo đơn vị</div>
                        <div class="desc">Vào mục Organization, tạo department/project trước khi nhập dữ liệu khảo sát.</div>
                    </div>
                    <div class="da-template-card">
                        <i class="fas fa-clipboard-list"></i>
                        <div class="name">2. Điền Survey</div>
                        <div class="desc">Nhập thông tin từng section bằng ngôn ngữ tự nhiên, chọn các module phù hợp ở mục Đề xuất module.</div>
                    </div>
                    <div class="da-template-card">
                        <i class="fas fa-wand-magic-sparkles"></i>
                        <div class="name">3. Dùng AI</div>
                        <div class="desc">Sử dụng AI Normalize hoặc AI Intake để chuẩn hóa, cấu trúc lại nội dung trước khi chốt báo cáo.</div>
                    </div>
                    <div class="da-template-card">
                        <i class="fas fa-image"></i>
                        <div class="name">4. Upload minh họa</div>
                        <div class="desc">Mỗi section có thể tải hình minh họa. Ảnh sẽ được kiểm tra, nén lại và gắn theo từng section.</div>
                    </div>
                    <div class="da-template-card">
                        <i class="fas fa-database"></i>
                        <div class="name">5. Hoàn thiện phân tích</div>
                        <div class="desc">Sử dụng Process Mapping, Data Architecture, Integration và Backlog để bổ sung chiều sâu nghiệp vụ.</div>
                    </div>
                    <div class="da-template-card">
                        <i class="fas fa-file-lines"></i>
                        <div class="name">6. Xuất báo cáo</div>
                        <div class="desc">Vào Reports để tạo báo cáo tổng hợp. Hình ảnh section và sơ đồ sẽ được đưa vào nội dung báo cáo.</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="da-section-card">
                    <div class="da-section-header">
                        <h3><i class="fas fa-key"></i> Cấu hình AI</h3>
                    </div>
                    <div class="da-scroll-box">
                        <p>Người dùng cấu hình AI tại mục <strong>AI API Key</strong>.</p>
                        <p>Hệ thống hỗ trợ `Groq` và `Ollama/local endpoint`.</p>
                        <p>Nếu chưa có API key, hệ thống sẽ cảnh báo và điều hướng đến đúng màn hình cấu hình.</p>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="da-section-card">
                    <div class="da-section-header">
                        <h3><i class="fas fa-sitemap"></i> Data Architecture</h3>
                    </div>
                    <div class="da-scroll-box">
                        <p>Module này dùng để quản lý entity, attribute, relationship, data dictionary, import/export và ER diagram.</p>
                        <p>Nên kiểm tra kỹ PK/FK, kiểu dữ liệu, quan hệ 1-n hoặc n-n trước khi chốt báo cáo.</p>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="da-section-card">
                    <div class="da-section-header">
                        <h3><i class="fas fa-shield-halved"></i> Lưu ý bảo mật</h3>
                    </div>
                    <div class="da-scroll-box">
                        <p>Mọi dữ liệu đã được tách theo user và department/project.</p>
                        <p>Ảnh upload được giới hạn định dạng, kiểm tra MIME, nén lại trước khi lưu và có thể bật quét antivirus trên server.</p>
                        <p>Thiết kế storage đã sẵn sàng để chuyển từ local sang S3 khi cần.</p>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="da-section-card">
                    <div class="da-section-header">
                        <h3><i class="fas fa-lightbulb"></i> Mẹo sử dụng</h3>
                    </div>
                    <div class="da-scroll-box">
                        <p>Input càng gần ngôn ngữ nghiệp vụ thực tế, AI normalize càng tốt.</p>
                        <p>Nên lưu Survey trước khi chạy AI Report để đảm bảo dữ liệu mới nhất đã được đồng bộ.</p>
                        <p>Với ảnh minh họa, ưu tiên sơ đồ rõ chữ, nền sáng, dung lượng vừa phải để kết quả nén vẫn dễ đọc.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
