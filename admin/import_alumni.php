<?php
$is_subfolder = true;
$page_title = "Import Alumni";
$active_page = "import_alumni";
$admin_prefix = "";
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../config/db.php';
require_admin();

$user_name = $_SESSION['admin_name'] ?? 'Admin';
$sidebar_avatar = 'https://cdn-icons-png.flaticon.com/512/2206/2206368.png';

require_once __DIR__ . '/../includes/header.php';

// Fetch history
$history = $pdo->query("SELECT * FROM import_history ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-wrapper">
    <?php render_sidebar($active_page); ?>
    
    <div class="dashboard-content-area">
        <?php include __DIR__ . '/../includes/top_nav.php'; ?>
        
        <main class="dashboard-workspace">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--theme-text-primary);">
                        <i class="fa-solid fa-file-import" style="color: var(--theme-accent-purple);"></i> Import Alumni Members
                    </h1>
                    <p style="color: var(--theme-text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
                        Bulk upload alumni records via CSV or XLSX.
                    </p>
                </div>
                <div>
                    <a href="download_alumni_template.php" class="btn btn-secondary">
                        <i class="fa-solid fa-download"></i> Download Template
                    </a>
                </div>
            </div>

            <!-- Upload Section -->
            <div class="glass-card" style="padding: 2rem; border-radius: 16px; margin-bottom: 2rem;" id="uploadSection">
                <h3 style="margin-bottom: 1rem;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload File</h3>
                <form id="uploadForm" enctype="multipart/form-data">
                    <div style="border: 2px dashed rgba(255,255,255,0.2); border-radius: 8px; padding: 3rem; text-align: center; background: rgba(0,0,0,0.2); cursor: pointer;" onclick="document.getElementById('fileInput').click()">
                        <i class="fa-solid fa-file-excel" style="font-size: 3rem; color: #10b981; margin-bottom: 1rem;"></i>
                        <h4 style="font-size: 1.2rem; margin-bottom: 0.5rem;">Click to Browse or Drag & Drop</h4>
                        <p style="color: var(--theme-text-muted);">Supports .csv, .xlsx, .pdf, images, and text files (Max 5MB)</p>
                        <input type="file" id="fileInput" name="import_file" accept=".csv, .xlsx, .pdf, image/*, .txt, .docx" style="display: none;" onchange="handleFileSelect(event)">
                    </div>
                    <div id="fileInfo" style="display:none; margin-top: 1rem; align-items: center; gap: 1rem;">
                        <span id="fileNameDisplay" style="font-weight: bold; color: var(--theme-accent-purple);"></span>
                        <button class="btn btn-secondary" onclick="clearFile()" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;">Clear</button>
                    </div>

                    <div style="margin-top: 2rem; background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                        <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-wand-magic-sparkles" style="color: #a855f7;"></i> Universal AI Extractor (Optional)</h4>
                        <p style="font-size: 0.85rem; color: var(--theme-text-muted); margin-bottom: 1rem;">Provide a Google Gemini API Key to intelligently extract alumni records from ANY unstructured file format (Screenshots, Scanned PDFs, Text files).</p>
                        <input type="password" id="geminiApiKey" class="form-control" placeholder="Enter Gemini API Key (Stored securely in browser)" onchange="saveApiKey()" style="margin-bottom: 0.5rem; background: rgba(0,0,0,0.2);">
                    </div>
                    <div style="margin-top: 1.5rem; text-align: right;">
                        <button type="button" class="btn btn-primary" id="btnPreview" onclick="previewImport()" disabled>
                            <i class="fa-solid fa-eye"></i> Preview Import
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview Section -->
            <div id="previewSection" style="display: none;">
                <div class="glass-card" style="padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem;">
                    <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <div style="flex: 1; background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; border-left: 4px solid #6366f1;">
                            <div style="font-size: 0.85rem; color: var(--theme-text-muted);">Total Rows</div>
                            <div style="font-size: 1.5rem; font-weight: bold;" id="statTotal">0</div>
                        </div>
                        <div style="flex: 1; background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; border-left: 4px solid #10b981;">
                            <div style="font-size: 0.85rem; color: var(--theme-text-muted);">Valid Records</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #10b981;" id="statValid">0</div>
                        </div>
                        <div style="flex: 1; background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; border-left: 4px solid #ef4444;">
                            <div style="font-size: 0.85rem; color: var(--theme-text-muted);">Invalid Rows</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #ef4444;" id="statInvalid">0</div>
                        </div>
                        <div style="flex: 1; background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; border-left: 4px solid #f59e0b;">
                            <div style="font-size: 0.85rem; color: var(--theme-text-muted);">Duplicates</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #f59e0b;" id="statDuplicate">0</div>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px;">
                        <h4 style="margin-bottom: 0.5rem;">Duplicate Handling Policy</h4>
                        <div style="display: flex; gap: 2rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="duplicate_action" value="skip" checked> Skip duplicate records
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="duplicate_action" value="update"> Update existing records
                            </label>
                        </div>
                    </div>

                    <div style="overflow-x: auto; max-height: 400px; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;">
                        <table class="table" style="width: 100%; white-space: nowrap;">
                            <thead style="position: sticky; top: 0; background: #1e1e2d; z-index: 1;">
                                <tr>
                                    <th>Row</th>
                                    <th>Status</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Enrollment ID</th>
                                    <th>Course</th>
                                    <th>Grad Year</th>
                                    <th>Errors</th>
                                </tr>
                            </thead>
                            <tbody id="previewTableBody">
                                <!-- Data injected via JS -->
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <button class="btn btn-secondary" onclick="cancelImport()">Cancel</button>
                            <button class="btn btn-secondary" onclick="exportToPDF()" id="btnExportPDF" style="margin-left: 0.5rem;"><i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> Export PDF</button>
                        </div>
                        <button class="btn btn-primary" id="btnExecuteImport" onclick="executeImport()">
                            <i class="fa-solid fa-play"></i> Import Valid Records
                        </button>
                    </div>
                </div>
            </div>

            <!-- Import History -->
            <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
                <h3 style="margin-bottom: 1rem;"><i class="fa-solid fa-clock-rotate-left"></i> Import History</h3>
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Filename</th>
                                <th>Total</th>
                                <th>Imported</th>
                                <th>Updated</th>
                                <th>Skipped</th>
                                <th>Failed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($history)): ?>
                                <tr><td colspan="8" style="text-align:center; padding:1rem; color:var(--theme-text-muted);">No import history found.</td></tr>
                            <?php else: ?>
                                <?php foreach($history as $h): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i', strtotime($h['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($h['filename']) ?></td>
                                    <td><?= $h['total_rows'] ?></td>
                                    <td><span style="color:#10b981;"><?= $h['imported_count'] ?></span></td>
                                    <td><span style="color:#3b82f6;"><?= $h['updated_count'] ?></span></td>
                                    <td><span style="color:#f59e0b;"><?= $h['skipped_count'] ?></span></td>
                                    <td><span style="color:#ef4444;"><?= $h['failed_count'] ?></span></td>
                                    <td>
                                        <?php if($h['status']=='completed'): ?>
                                            <span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981;">Completed</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b;"><?= ucfirst($h['status']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
let importPayload = null;

// Load API key from local storage
document.addEventListener('DOMContentLoaded', () => {
    const key = localStorage.getItem('gemini_api_key');
    if (key) document.getElementById('geminiApiKey').value = key;
});

function saveApiKey() {
    localStorage.setItem('gemini_api_key', document.getElementById('geminiApiKey').value.trim());
}

function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        document.getElementById('fileNameDisplay').textContent = file.name + ' (' + (file.size/1024).toFixed(2) + ' KB)';
        document.getElementById('fileInfo').style.display = 'flex';
        document.getElementById('btnPreview').disabled = false;
    }
}

function clearFile() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('btnPreview').disabled = true;
    document.getElementById('previewSection').style.display = 'none';
}

function cancelImport() {
    clearFile();
}

async function previewImport() {
    const file = document.getElementById('fileInput').files[0];
    if(!file) return;

    const btn = document.getElementById('btnPreview');
    const ogText = btn.innerHTML;
    const apiKey = document.getElementById('geminiApiKey').value.trim();

    const isImage = file.name.match(/\.(png|jpe?g)$/i);
    const isText = file.name.match(/\.(txt)$/i);

    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    btn.disabled = true;

    try {
        let formData = new FormData();
        
        if (apiKey) {
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> AI Analyzing...';
            // Convert file to Base64
            const base64Data = await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result.split(',')[1]);
                reader.readAsDataURL(file);
            });

            // Map mime types
            let mimeType = file.type;
            if (file.name.endsWith('.pdf')) mimeType = 'application/pdf';
            if (file.name.endsWith('.txt') || file.name.endsWith('.csv')) mimeType = 'text/plain';

            const aiResponse = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${apiKey}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    contents: [{
                        parts: [
                            { text: "Extract all alumni records from this document. Return ONLY a valid JSON array of objects. Use these exact keys: First Name, Last Name, Email, Phone, Enrollment ID, Grad Year, Course, Company, Position, Industry, LinkedIn. Leave missing fields blank." },
                            { inlineData: { mimeType: mimeType, data: base64Data } }
                        ]
                    }],
                    generationConfig: { responseMimeType: "application/json" }
                })
            });

            const aiData = await aiResponse.json();
            if (aiData.error) throw new Error(aiData.error.message);

            let textResp = aiData.candidates[0].content.parts[0].text;
            // Remove potential markdown code blocks
            textResp = textResp.replace(/```json/g, '').replace(/```/g, '').trim();
            const records = JSON.parse(textResp);
            
            // Convert to 2D array format for backend
            const headers = ["First Name", "Last Name", "Email", "Phone", "Enrollment ID", "Grad Year", "Course", "Company", "Position", "Industry", "LinkedIn"];
            const rows2D = [headers];
            
            records.forEach(r => {
                rows2D.push([
                    r['First Name']||'', r['Last Name']||'', r['Email']||'', r['Phone']||'', r['Enrollment ID']||'', 
                    r['Grad Year']||'', r['Course']||'', r['Company']||'', r['Position']||'', r['Industry']||'', r['LinkedIn']||''
                ]);
            });

            formData.append('action', 'preview_ai');
            formData.append('ai_data', JSON.stringify(rows2D));
            formData.append('filename', file.name);
        } else if (isImage || isText) {
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Local OCR Processing...';
            
            let rawText = '';
            if (isImage) {
                const worker = await Tesseract.createWorker("eng");
                const ret = await worker.recognize(file);
                await worker.terminate();
                rawText = ret.data.text;
            } else {
                rawText = await file.text();
            }

            const lines = rawText.split('\n');
            const headers = ["First Name", "Last Name", "Email", "Phone", "Enrollment ID", "Grad Year", "Course", "Company", "Position", "Industry", "LinkedIn"];
            const rows2D = [headers];
            
            lines.forEach(line => {
                if (!line.trim()) return;
                
                let emailMatch = line.match(/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/);
                let phoneMatch = line.match(/\b\d{10}\b/);
                let yearMatch = line.match(/\b(19|20)\d{2}\b/);
                
                let remaining = line;
                if (emailMatch) remaining = remaining.replace(emailMatch[0], '');
                if (phoneMatch) remaining = remaining.replace(phoneMatch[0], '');
                if (yearMatch) remaining = remaining.replace(yearMatch[0], '');
                
                remaining = remaining.replace(/[^\w\s]/g, '').trim().split(/\s+/);
                let firstName = remaining[0] || '';
                let lastName = remaining.length > 1 ? remaining.slice(1).join(' ') : '';
                
                // Only push if we found something useful
                if (emailMatch || phoneMatch || firstName) {
                    rows2D.push([
                        firstName, lastName, 
                        emailMatch ? emailMatch[0] : '', 
                        phoneMatch ? phoneMatch[0] : '', 
                        '', // Enrollment ID hard to guess without context
                        yearMatch ? yearMatch[0] : '', 
                        '', '', '', '', ''
                    ]);
                }
            });

            formData.append('action', 'preview_ai');
            formData.append('ai_data', JSON.stringify(rows2D));
            formData.append('filename', file.name);
        } else {
            formData.append('action', 'preview');
            formData.append('import_file', file);
        }

        const res = await fetch('../api/import_alumni_action.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await res.json();
        btn.innerHTML = ogText;
        btn.disabled = false;

        if (data.status === 'error') {
            alert('Error: ' + data.message);
            return;
        }

        importPayload = data;
        renderPreview(data);
    } catch (err) {
        btn.innerHTML = ogText;
        btn.disabled = false;
        alert('An error occurred: ' + err.message);
    }
}

function renderPreview(data) {
    document.getElementById('previewSection').style.display = 'block';
    document.getElementById('statTotal').textContent = data.stats.total;
    document.getElementById('statValid').textContent = data.stats.valid;
    document.getElementById('statInvalid').textContent = data.stats.invalid;
    document.getElementById('statDuplicate').textContent = data.stats.duplicate;

    const tbody = document.getElementById('previewTableBody');
    let html = '';

    data.preview.forEach(row => {
        let statusBadge = '';
        if (row.status === 'valid') statusBadge = '<span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981;">Valid</span>';
        else if (row.status === 'invalid') statusBadge = '<span class="badge" style="background: rgba(239,68,68,0.15); color: #ef4444;">Invalid</span>';
        else if (row.status === 'duplicate') statusBadge = '<span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b;">Duplicate</span>';

        html += `
            <tr style="background: ${row.status === 'invalid' ? 'rgba(239,68,68,0.05)' : 'transparent'}">
                <td>${row.row}</td>
                <td>${statusBadge}</td>
                <td>${row.name || '-'}</td>
                <td>${row.email || '-'}</td>
                <td>${row.phone || '-'}</td>
                <td>${row.enrollment_id || '-'}</td>
                <td>${row.course || '-'}</td>
                <td>${row.grad_year || '-'}</td>
                <td style="color: #ef4444; font-size: 0.85rem;">${row.errors}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function executeImport() {
    if(!importPayload) return;
    if(!confirm("Are you sure you want to import these records?")) return;

    const btn = document.getElementById('btnExecuteImport');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';
    btn.disabled = true;

    const duplicateAction = document.querySelector('input[name="duplicate_action"]:checked').value;

    const formData = new FormData();
    formData.append('action', 'import');
    formData.append('payload', JSON.stringify(importPayload));
    formData.append('duplicate_action', duplicateAction);

    fetch('../api/import_alumni_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'error') {
            alert('Error: ' + data.message);
            btn.innerHTML = '<i class="fa-solid fa-play"></i> Import Valid Records';
            btn.disabled = false;
            return;
        }

        alert(`Import Complete!\nImported: ${data.imported}\nUpdated: ${data.updated}\nSkipped: ${data.skipped}\nFailed: ${data.failed}`);
        window.location.reload();
    })
    .catch(err => {
        btn.innerHTML = '<i class="fa-solid fa-play"></i> Import Valid Records';
        btn.disabled = false;
        alert('A network error occurred during import.');
    });
}

function exportToPDF() {
    if(!importPayload || !importPayload.preview || importPayload.preview.length === 0) {
        alert("No data available to export.");
        return;
    }
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('landscape');
    
    doc.text("Alumni Import Preview Report", 14, 15);
    
    const tableColumn = ["Row", "Status", "Name", "Email", "Phone", "Enroll. ID", "Course", "Grad Year", "Errors"];
    const tableRows = [];
    
    importPayload.preview.forEach(row => {
        const rowData = [
            row.row,
            row.status.toUpperCase(),
            row.name || '-',
            row.email || '-',
            row.phone || '-',
            row.enrollment_id || '-',
            row.course || '-',
            row.grad_year || '-',
            row.errors || ''
        ];
        tableRows.push(rowData);
    });
    
    doc.autoTable({
        head: [tableColumn],
        body: tableRows,
        startY: 20,
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [99, 102, 241] },
        didParseCell: function(data) {
            if(data.section === 'body' && data.column.index === 1) {
                if(data.cell.raw === 'VALID') data.cell.styles.textColor = [16, 185, 129];
                if(data.cell.raw === 'INVALID') data.cell.styles.textColor = [239, 68, 68];
                if(data.cell.raw === 'DUPLICATE') data.cell.styles.textColor = [245, 158, 11];
            }
        }
    });
    
    doc.save(`alumni_import_preview_${new Date().getTime()}.pdf`);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
