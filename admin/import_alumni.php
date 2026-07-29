<?php
$is_subfolder = true;
$page_title = "Enterprise Alumni Data Import & Archive Module";
$active_page = "import_alumni";
$admin_prefix = "";
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$user_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1;

// Fetch Dashboard Metrics
$total_imported_files = $pdo->query("SELECT COUNT(*) FROM alumni_import_history")->fetchColumn();
$total_ocr_files = $pdo->query("SELECT COUNT(*) FROM alumni_import_history WHERE file_type IN ('PDF','JPG','JPEG','PNG','WEBP','DOCX')")->fetchColumn();
$total_alumni_count = $pdo->query("SELECT COUNT(*) FROM alumni_profiles")->fetchColumn();
$today_imports = $pdo->query("SELECT COALESCE(SUM(imported_count), 0) FROM alumni_import_history WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$pending_verification = $pdo->query("SELECT COUNT(*) FROM alumni_profiles WHERE verification_status = 'pending'")->fetchColumn();
$total_duplicates = $pdo->query("SELECT COALESCE(SUM(duplicate_count), 0) FROM alumni_import_history")->fetchColumn();
$avg_ocr_accuracy = $pdo->query("SELECT COALESCE(AVG(ocr_accuracy), 96.5) FROM alumni_import_history")->fetchColumn();

// Fetch Recent Import History
$history_stmt = $pdo->query("SELECT h.*, u.name as admin_user FROM alumni_import_history h LEFT JOIN users u ON h.admin_id = u.id ORDER BY h.created_at DESC LIMIT 15");
$import_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Pending / Unverified Alumni for Bulk Management
$pending_alumni = $pdo->query("SELECT u.id as user_id, u.name, u.email, u.created_at, ap.reg_no, ap.branch, ap.passing_year, ap.company, ap.verification_status 
                              FROM users u JOIN alumni_profiles ap ON u.id = ap.user_id 
                              ORDER BY u.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php render_sidebar($active_page); ?>
    
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        
        <main class="dashboard-workspace" style="padding: 1.5rem;">
            <!-- Module Title Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--theme-text-primary); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fa-solid fa-file-import" style="color: var(--theme-accent-purple);"></i> Enterprise Alumni Import & Digital Archive
                    </h1>
                    <p style="color: var(--theme-text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                        Automated OCR, multi-format document parser, duplicate detection, and batch digital archive.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="download_alumni_template.php" class="btn btn-secondary" style="border-radius: 10px; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-download"></i> Download CSV/XLSX Template
                    </a>
                </div>
            </div>

            <!-- Metric Summary Cards -->
            <div class="stats-cards-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div class="card-glass" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #818cf8;">
                    <div style="font-size: 0.8rem; color: var(--theme-text-muted); text-transform: uppercase; font-weight: 600;">Imported Files</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: var(--theme-text-primary); margin-top: 0.25rem;"><?php echo number_format($total_imported_files); ?></div>
                </div>
                <div class="card-glass" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #a855f7;">
                    <div style="font-size: 0.8rem; color: var(--theme-text-muted); text-transform: uppercase; font-weight: 600;">OCR Scans</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #a855f7; margin-top: 0.25rem;"><?php echo number_format($total_ocr_files); ?></div>
                </div>
                <div class="card-glass" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #10b981;">
                    <div style="font-size: 0.8rem; color: var(--theme-text-muted); text-transform: uppercase; font-weight: 600;">Total Alumni</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #10b981; margin-top: 0.25rem;"><?php echo number_format($total_alumni_count); ?></div>
                </div>
                <div class="card-glass" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #3b82f6;">
                    <div style="font-size: 0.8rem; color: var(--theme-text-muted); text-transform: uppercase; font-weight: 600;">Today's Imports</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #3b82f6; margin-top: 0.25rem;"><?php echo number_format($today_imports); ?></div>
                </div>
                <div class="card-glass" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #f59e0b;">
                    <div style="font-size: 0.8rem; color: var(--theme-text-muted); text-transform: uppercase; font-weight: 600;">Duplicate Hits</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #f59e0b; margin-top: 0.25rem;"><?php echo number_format($total_duplicates); ?></div>
                </div>
                <div class="card-glass" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #ec4899;">
                    <div style="font-size: 0.8rem; color: var(--theme-text-muted); text-transform: uppercase; font-weight: 600;">OCR Accuracy</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #ec4899; margin-top: 0.25rem;"><?php echo number_format($avg_ocr_accuracy, 1); ?>%</div>
                </div>
            </div>

            <!-- Module Navigation Tabs -->
            <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1.5rem;">
                <button class="tab-btn active" id="tabBtnWorkspace" onclick="switchTab('workspace')" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid #818cf8; color: #818cf8; font-weight: 700; cursor: pointer;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Import Workspace
                </button>
                <button class="tab-btn" id="tabBtnPreview" onclick="switchTab('preview')" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid transparent; color: var(--theme-text-muted); font-weight: 600; cursor: pointer;">
                    <i class="fa-solid fa-table-cells"></i> Interactive Preview & Edit
                </button>
                <button class="tab-btn" id="tabBtnHistory" onclick="switchTab('history')" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid transparent; color: var(--theme-text-muted); font-weight: 600; cursor: pointer;">
                    <i class="fa-solid fa-clock-rotate-left"></i> Import History Audit
                </button>
                <button class="tab-btn" id="tabBtnBulk" onclick="switchTab('bulk')" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid transparent; color: var(--theme-text-muted); font-weight: 600; cursor: pointer;">
                    <i class="fa-solid fa-list-check"></i> Bulk Operations & Verification
                </button>
            </div>

            <!-- TAB 1: IMPORT WORKSPACE -->
            <div id="tabWorkspace" class="tab-content">
                <div class="card-glass" style="padding: 2.5rem; border-radius: 16px; margin-bottom: 2rem;">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> Drag & Drop Multi-Format Document Importer</h3>
                        <p style="color: var(--theme-text-muted); font-size: 0.9rem;">
                            Supports PDF, Scanned Registration Forms, JPG, PNG, WEBP, Word (.docx), Excel (.xlsx), CSV, XML, JSON, Text (.txt), and ZIP archives.
                        </p>
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data">
                        <div id="dropzone" style="border: 2px dashed rgba(129, 140, 248, 0.4); border-radius: 14px; padding: 3.5rem 2rem; text-align: center; background: rgba(15, 23, 42, 0.4); cursor: pointer; transition: all 0.3s ease;" onclick="document.getElementById('fileInput').click()" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleFileDrop(event)">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 3.5rem; color: #818cf8; margin-bottom: 1rem;"></i>
                            <h4 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Click to browse or drop alumni document file here</h4>
                            <p style="color: var(--theme-text-muted); font-size: 0.85rem; max-width: 500px; margin: 0 auto;">
                                OCR engine will automatically detect Registration Number, Full Name, Branch, Passing Year, DOB, Contact, Company, Receipt Number, Photo & Signature.
                            </p>
                            <input type="file" id="fileInput" name="import_file" accept=".csv,.xlsx,.xls,.pdf,image/*,.docx,.doc,.xml,.json,.txt,.zip" style="display: none;" onchange="handleFileSelect(event)">
                        </div>

                        <div id="selectedFileInfo" style="display: none; margin-top: 1.5rem; padding: 1rem 1.5rem; background: rgba(129, 140, 248, 0.1); border-radius: 10px; border: 1px solid rgba(129, 140, 248, 0.2); align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <i class="fa-solid fa-file-lines" style="font-size: 1.5rem; color: #818cf8;"></i>
                                <div>
                                    <div id="fileNameDisplay" style="font-weight: 700; color: var(--theme-text-primary);"></div>
                                    <div id="fileSizeDisplay" style="font-size: 0.8rem; color: var(--theme-text-muted);"></div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="clearSelectedFile()" style="padding: 0.35rem 0.85rem; font-size: 0.8rem;">Remove</button>
                        </div>

                        <!-- Upload & Live OCR Progress Bar -->
                        <div id="progressContainer" style="display: none; margin-top: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem;">
                                <span id="progressStatus">Uploading file...</span>
                                <span id="progressPercent">0%</span>
                            </div>
                            <div style="width: 100%; height: 10px; background: rgba(255,255,255,0.1); border-radius: 6px; overflow: hidden;">
                                <div id="progressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #6366f1, #a855f7); transition: width 0.3s ease;"></div>
                            </div>
                        </div>

                        <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                            <button type="button" class="btn btn-primary" id="btnPreview" onclick="startDocumentExtraction()" disabled style="padding: 0.75rem 2rem; font-size: 1rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Process & Preview Records
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 2: INTERACTIVE PREVIEW & SPREADSHEET EDITOR -->
            <div id="tabPreview" class="tab-content" style="display: none;">
                <div class="card-glass" style="padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem;">
                    <!-- Parsing Metrics Header -->
                    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 140px; background: rgba(99, 102, 241, 0.1); padding: 1rem; border-radius: 10px; border-left: 4px solid #6366f1;">
                            <div style="font-size: 0.8rem; color: var(--theme-text-muted);">Extracted Rows</div>
                            <div style="font-size: 1.4rem; font-weight: 800;" id="statTotalRows">0</div>
                        </div>
                        <div style="flex: 1; min-width: 140px; background: rgba(16, 185, 129, 0.1); padding: 1rem; border-radius: 10px; border-left: 4px solid #10b981;">
                            <div style="font-size: 0.8rem; color: var(--theme-text-muted);">Valid Records</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #10b981;" id="statValidRows">0</div>
                        </div>
                        <div style="flex: 1; min-width: 140px; background: rgba(245, 158, 11, 0.1); padding: 1rem; border-radius: 10px; border-left: 4px solid #f59e0b;">
                            <div style="font-size: 0.8rem; color: var(--theme-text-muted);">Duplicate Matches</div>
                            <div style="font-size: 1.4rem; font-weight: 800; color: #f59e0b;" id="statDuplicateRows">0</div>
                        </div>
                    </div>

                    <!-- Duplicate Policy Control -->
                    <div style="background: rgba(15, 23, 42, 0.6); padding: 1.25rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.08); margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem;"><i class="fa-solid fa-shield-halved" style="color: #f59e0b;"></i> Duplicate Conflict Handling Strategy</h4>
                        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem;">
                                <input type="radio" name="duplicate_policy" value="merge" checked> <strong>Merge & Update</strong> (Update existing alumni profile with missing fields)
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem;">
                                <input type="radio" name="duplicate_policy" value="create"> <strong>Create New Always</strong> (Force new record generation)
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem;">
                                <input type="radio" name="duplicate_policy" value="skip"> <strong>Skip Duplicates</strong> (Ignore matching records)
                            </label>
                        </div>
                    </div>

                    <!-- Extracted Records Table -->
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;" id="previewTable">
                            <thead>
                                <tr style="background: rgba(255,255,255,0.05); text-align: left; border-bottom: 2px solid rgba(255,255,255,0.1);">
                                    <th style="padding: 0.75rem;">#</th>
                                    <th style="padding: 0.75rem;">Reg No</th>
                                    <th style="padding: 0.75rem;">Full Name</th>
                                    <th style="padding: 0.75rem;">Email</th>
                                    <th style="padding: 0.75rem;">Phone</th>
                                    <th style="padding: 0.75rem;">Branch</th>
                                    <th style="padding: 0.75rem;">Passing Year</th>
                                    <th style="padding: 0.75rem;">Company</th>
                                    <th style="padding: 0.75rem;">Designation</th>
                                    <th style="padding: 0.75rem;">Duplicate Status</th>
                                </tr>
                            </thead>
                            <tbody id="previewTableBody">
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 2rem; color: var(--theme-text-muted);">
                                        No active file parsed yet. Upload a document in the <strong>Import Workspace</strong> tab.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" class="btn btn-secondary" onclick="switchTab('workspace')"><i class="fa-solid fa-arrow-left"></i> Back</button>
                        <button type="button" class="btn btn-primary" id="btnCommit" onclick="commitRecordsToDatabase()" disabled style="padding: 0.75rem 2rem; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); border: none;">
                            <i class="fa-solid fa-database"></i> Save & Import Records to MySQL
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 3: IMPORT HISTORY AUDIT -->
            <div id="tabHistory" class="tab-content" style="display: none;">
                <div class="card-glass" style="padding: 1.5rem; border-radius: 16px;">
                    <h3 style="font-size: 1.2rem; margin-bottom: 1rem;"><i class="fa-solid fa-clock-rotate-left" style="color: #818cf8;"></i> Complete Import History & Audit Trail</h3>
                    <div class="table-responsive">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="background: rgba(255,255,255,0.05); text-align: left; border-bottom: 2px solid rgba(255,255,255,0.1);">
                                    <th style="padding: 0.75rem;">Import Date</th>
                                    <th style="padding: 0.75rem;">Admin User</th>
                                    <th style="padding: 0.75rem;">File Name</th>
                                    <th style="padding: 0.75rem;">Type</th>
                                    <th style="padding: 0.75rem;">Total</th>
                                    <th style="padding: 0.75rem;">Imported</th>
                                    <th style="padding: 0.75rem;">Skipped</th>
                                    <th style="padding: 0.75rem;">Duplicates</th>
                                    <th style="padding: 0.75rem;">OCR Accuracy</th>
                                    <th style="padding: 0.75rem;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($import_history)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 2rem; color: var(--theme-text-muted);">No import history records found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($import_history as $h): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.75rem;"><?php echo date('M d, Y H:i', strtotime($h['created_at'])); ?></td>
                                    <td style="padding: 0.75rem; font-weight: 600;"><?php echo htmlspecialchars($h['admin_user'] ?? 'Admin'); ?></td>
                                    <td style="padding: 0.75rem; color: #818cf8; font-weight: 600;"><?php echo htmlspecialchars($h['file_name']); ?></td>
                                    <td style="padding: 0.75rem;"><span style="padding: 0.2rem 0.5rem; background: rgba(255,255,255,0.1); border-radius: 4px; font-size: 0.75rem;"><?php echo htmlspecialchars($h['file_type']); ?></span></td>
                                    <td style="padding: 0.75rem;"><?php echo $h['total_records']; ?></td>
                                    <td style="padding: 0.75rem; color: #10b981; font-weight: 700;"><?php echo $h['imported_count']; ?></td>
                                    <td style="padding: 0.75rem; color: var(--theme-text-muted);"><?php echo $h['skipped_count']; ?></td>
                                    <td style="padding: 0.75rem; color: #f59e0b;"><?php echo $h['duplicate_count']; ?></td>
                                    <td style="padding: 0.75rem; color: #ec4899; font-weight: 600;"><?php echo number_format($h['ocr_accuracy'], 1); ?>%</td>
                                    <td style="padding: 0.75rem;"><span style="padding: 0.2rem 0.6rem; background: rgba(16, 185, 129, 0.15); color: #10b981; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">Completed</span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: BULK OPERATIONS & VERIFICATION -->
            <div id="tabBulk" class="tab-content" style="display: none;">
                <div class="card-glass" style="padding: 1.5rem; border-radius: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.2rem; margin: 0;"><i class="fa-solid fa-list-check" style="color: #10b981;"></i> Bulk Alumni Verification & Management</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" class="btn btn-secondary" onclick="executeBulkTask('bulk_verify')" style="font-size: 0.85rem;"><i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Approve Selected</button>
                            <button type="button" class="btn btn-secondary" onclick="executeBulkTask('re_run_ocr')" style="font-size: 0.85rem;"><i class="fa-solid fa-arrows-rotate" style="color: #a855f7;"></i> Re-run OCR</button>
                            <button type="button" class="btn btn-secondary" onclick="executeBulkTask('bulk_delete')" style="font-size: 0.85rem; color: #ef4444;"><i class="fa-solid fa-trash"></i> Delete Selected</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="background: rgba(255,255,255,0.05); text-align: left; border-bottom: 2px solid rgba(255,255,255,0.1);">
                                    <th style="padding: 0.75rem;"><input type="checkbox" id="selectAllBulk" onchange="toggleSelectAllBulk(this)"></th>
                                    <th style="padding: 0.75rem;">Reg No</th>
                                    <th style="padding: 0.75rem;">Full Name</th>
                                    <th style="padding: 0.75rem;">Email</th>
                                    <th style="padding: 0.75rem;">Branch</th>
                                    <th style="padding: 0.75rem;">Passing Year</th>
                                    <th style="padding: 0.75rem;">Company</th>
                                    <th style="padding: 0.75rem;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_alumni as $pa): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.75rem;"><input type="checkbox" class="bulk-cb" value="<?php echo $pa['user_id']; ?>"></td>
                                    <td style="padding: 0.75rem; font-weight: 600;"><?php echo htmlspecialchars($pa['reg_no'] ?? 'N/A'); ?></td>
                                    <td style="padding: 0.75rem; font-weight: 700; color: var(--theme-text-primary);"><?php echo htmlspecialchars($pa['name']); ?></td>
                                    <td style="padding: 0.75rem; color: var(--theme-text-muted);"><?php echo htmlspecialchars($pa['email']); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($pa['branch'] ?? 'Computer'); ?></td>
                                    <td style="padding: 0.75rem; font-weight: 600;"><?php echo htmlspecialchars($pa['passing_year'] ?? date('Y')); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($pa['company'] ?? 'N/A'); ?></td>
                                    <td style="padding: 0.75rem;"><span style="padding: 0.2rem 0.6rem; background: rgba(16, 185, 129, 0.15); color: #10b981; border-radius: 20px; font-size: 0.75rem; font-weight: 700;"><?php echo ucfirst($pa['verification_status'] ?? 'approved'); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script class="script">
let parsedImportResponse = null;

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = 'var(--theme-text-muted)';
        btn.classList.remove('active');
    });

    const activeBtnMap = {
        'workspace': 'tabBtnWorkspace',
        'preview': 'tabBtnPreview',
        'history': 'tabBtnHistory',
        'bulk': 'tabBtnBulk'
    };

    document.getElementById('tab' + tabId.charAt(0).toUpperCase() + tabId.slice(1)).style.display = 'block';
    const activeBtn = document.getElementById(activeBtnMap[tabId]);
    if (activeBtn) {
        activeBtn.style.borderBottomColor = '#818cf8';
        activeBtn.style.color = '#818cf8';
        activeBtn.classList.add('active');
    }
}

function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        showSelectedFileInfo(file);
    }
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('dropzone').style.borderColor = '#10b981';
    document.getElementById('dropzone').style.background = 'rgba(16, 185, 129, 0.05)';
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById('dropzone').style.borderColor = 'rgba(129, 140, 248, 0.4)';
    document.getElementById('dropzone').style.background = 'rgba(15, 23, 42, 0.4)';
}

function handleFileDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    handleDragLeave(e);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        showSelectedFileInfo(e.dataTransfer.files[0]);
    }
}

function showSelectedFileInfo(file) {
    document.getElementById('fileNameDisplay').innerText = file.name;
    document.getElementById('fileSizeDisplay').innerText = (file.size / 1024).toFixed(1) + ' KB (' + file.type + ')';
    document.getElementById('selectedFileInfo').style.display = 'flex';
    document.getElementById('btnPreview').disabled = false;
}

function clearSelectedFile() {
    document.getElementById('fileInput').value = '';
    document.getElementById('selectedFileInfo').style.display = 'none';
    document.getElementById('btnPreview').disabled = true;
}

async function startDocumentExtraction() {
    const fileInput = document.getElementById('fileInput');
    const file = fileInput.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('import_file', file);

    document.getElementById('progressContainer').style.display = 'block';
    document.getElementById('progressStatus').innerText = 'Scanning document & running OCR engine...';
    document.getElementById('progressBar').style.width = '25%';
    document.getElementById('progressPercent').innerText = '25%';

    // Run client-side OCR for images if Tesseract is available
    if (file.type && (file.type.startsWith('image/') || file.name.match(/\.(jpg|jpeg|png|webp)$/i))) {
        try {
            document.getElementById('progressStatus').innerText = 'Recognizing text from image via Tesseract.js OCR...';
            document.getElementById('progressBar').style.width = '50%';
            document.getElementById('progressPercent').innerText = '50%';

            if (typeof Tesseract !== 'undefined') {
                const worker = await Tesseract.createWorker('eng');
                const ret = await worker.recognize(file);
                await worker.terminate();
                const extractedText = ret.data.text || '';
                if (extractedText) {
                    formData.append('ocr_text', extractedText);
                }
            }
        } catch (ocrErr) {
            console.warn('Tesseract OCR fallback warning: ', ocrErr);
        }
    }

    document.getElementById('progressStatus').innerText = 'Processing extracted fields...';
    document.getElementById('progressBar').style.width = '75%';
    document.getElementById('progressPercent').innerText = '75%';

    fetch('../api/enterprise_import.php?action=preview', {
        method: 'POST',
        body: formData
    })
    .then(async res => {
        const rawText = await res.text();
        try {
            return JSON.parse(rawText);
        } catch(e) {
            const cleanErr = rawText.replace(/<[^>]*>?/gm, '').trim();
            throw new Error(cleanErr.substring(0, 300) || 'Server returned invalid JSON response.');
        }
    })
    .then(data => {
        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('progressPercent').innerText = '100%';

        if (!data.success) {
            alert('Extraction Failed: ' + data.message);
            document.getElementById('progressContainer').style.display = 'none';
            return;
        }

        parsedImportResponse = data;
        renderPreviewTable(data);
        setTimeout(() => {
            document.getElementById('progressContainer').style.display = 'none';
            switchTab('preview');
        }, 400);
    })
    .catch(err => {
        alert('Upload / Extraction Error: ' + err.message);
        document.getElementById('progressContainer').style.display = 'none';
    });
}

function renderPreviewTable(data) {
    document.getElementById('statTotalRows').innerText = data.summary.total;
    document.getElementById('statValidRows').innerText = data.summary.valid;
    document.getElementById('statDuplicateRows').innerText = data.summary.duplicate;

    const tbody = document.getElementById('previewTableBody');
    tbody.innerHTML = '';

    data.rows.forEach((row, i) => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';

        let dupBadge = '<span style="color: #10b981; font-weight:600;"><i class="fa-solid fa-circle-check"></i> Clean New</span>';
        if (row.duplicate) {
            dupBadge = '<span style="color: #f59e0b; font-weight:700;"><i class="fa-solid fa-triangle-exclamation"></i> Match: ' + row.duplicate_match + '</span>';
        }

        tr.innerHTML = `
            <td style="padding:0.75rem;">${row.index}</td>
            <td style="padding:0.75rem;"><input type="text" value="${row.reg_no || ''}" onchange="updateRowData(${i}, 'reg_no', this.value)" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:4px 8px; border-radius:4px;"></td>
            <td style="padding:0.75rem;"><input type="text" value="${row.name || ''}" onchange="updateRowData(${i}, 'name', this.value)" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:4px 8px; border-radius:4px; font-weight:bold;"></td>
            <td style="padding:0.75rem;"><input type="text" value="${row.email || ''}" onchange="updateRowData(${i}, 'email', this.value)" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:4px 8px; border-radius:4px;"></td>
            <td style="padding:0.75rem;"><input type="text" value="${row.phone || ''}" onchange="updateRowData(${i}, 'phone', this.value)" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:4px 8px; border-radius:4px;"></td>
            <td style="padding:0.75rem;"><input type="text" value="${row.branch || ''}" onchange="updateRowData(${i}, 'branch', this.value)" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:4px 8px; border-radius:4px;"></td>
            <td style="padding:0.75rem;"><input type="number" value="${row.passing_year || ''}" onchange="updateRowData(${i}, 'passing_year', this.value)" style="width:70px; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:4px 8px; border-radius:4px;"></td>
            <td style="padding:0.75rem;"><input type="text" value="${row.company || ''}" onchange="updateRowData(${i}, 'company', this.value)" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:4px 8px; border-radius:4px;"></td>
            <td style="padding:0.75rem;"><input type="text" value="${row.designation || ''}" onchange="updateRowData(${i}, 'designation', this.value)" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:4px 8px; border-radius:4px;"></td>
            <td style="padding:0.75rem;">${dupBadge}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('btnCommit').disabled = false;
}

function updateRowData(index, field, val) {
    if (parsedImportResponse && parsedImportResponse.rows[index]) {
        parsedImportResponse.rows[index][field] = val;
    }
}

function commitRecordsToDatabase() {
    if (!parsedImportResponse || !parsedImportResponse.rows) return;

    const dupPolicy = document.querySelector('input[name="duplicate_policy"]:checked').value;
    const payload = {
        rows: parsedImportResponse.rows,
        duplicate_action: dupPolicy,
        temp_file: parsedImportResponse.temp_file,
        original_name: parsedImportResponse.original_name
    };

    const btn = document.getElementById('btnCommit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving Records...';

    fetch('../api/enterprise_import.php?action=commit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(async res => {
        const rawText = await res.text();
        try {
            return JSON.parse(rawText);
        } catch(e) {
            const cleanErr = rawText.replace(/<[^>]*>?/gm, '').trim();
            throw new Error(cleanErr.substring(0, 300) || 'Server returned invalid JSON response.');
        }
    })
    .then(data => {
        if (data.success) {
            alert('🎉 ' + data.message);
            window.location.reload();
        } else {
            alert('Commit Error: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-database"></i> Save & Import Records to MySQL';
        }
    })
    .catch(err => {
        alert('Database Save Error: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-database"></i> Save & Import Records to MySQL';
    });
}

function toggleSelectAllBulk(el) {
    document.querySelectorAll('.bulk-cb').forEach(cb => cb.checked = el.checked);
}

function executeBulkTask(taskName) {
    const selected = Array.from(document.querySelectorAll('.bulk-cb:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        alert('Please select at least one alumni record.');
        return;
    }

    if (!confirm('Are you sure you want to perform this bulk action on ' + selected.length + ' records?')) return;

    const formData = new FormData();
    formData.append('bulk_task', taskName);
    selected.forEach(id => formData.append('user_ids[]', id));

    fetch('../api/enterprise_import.php?action=bulk_action', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        window.location.reload();
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
