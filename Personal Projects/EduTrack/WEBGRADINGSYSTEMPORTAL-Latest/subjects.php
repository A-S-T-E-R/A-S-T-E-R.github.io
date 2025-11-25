<?php
/*************************************************
 * subjects.php
 * Single-file CRUD for tbl_subjects
 *************************************************/

/////// CONFIG: Update to your DB //////
$DB_HOST = "localhost";
$DB_NAME = "school_portal";
$DB_USER = "your_user";
$DB_PASS = "your_password";
////////////////////////////////////////

header("X-Content-Type-Options: nosniff");

function db() {
  static $pdo = null;
  global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
  if ($pdo === null) {
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

function json_response($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($action) {
  try {
    $pdo = db();

    if ($action === 'list' && $method === 'GET') {
      $showArchived = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;

      $stmt = $pdo->prepare("
        SELECT id, subject_name, year_level_id, semester, archived
        FROM tbl_subjects
        WHERE archived = :arch
        ORDER BY year_level_id, subject_name
      ");
      $stmt->execute([':arch' => $showArchived]);
      $rows = $stmt->fetchAll();
      json_response(['ok' => true, 'data' => $rows]);
    }

    if ($action === 'create' && $method === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      $subject_name = trim($input['subject_name'] ?? '');
      $year_level_id = (int)($input['year_level_id'] ?? 0);
      $semester = $input['semester'] ?? 'Full Year';

      if ($subject_name === '' || $year_level_id <= 0 || !in_array($semester, ['1','2','Full Year'], true)) {
        json_response(['ok' => false, 'error' => 'Invalid input.'], 400);
      }

      $stmt = $pdo->prepare("
        INSERT INTO tbl_subjects (subject_name, year_level_id, semester, archived)
        VALUES (:name, :level, :sem, 0)
      ");
      try {
        $stmt->execute([
          ':name' => $subject_name,
          ':level' => $year_level_id,
          ':sem' => $semester
        ]);
      } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
          json_response(['ok' => false, 'error' => 'Duplicate: subject_name + year_level_id must be unique.'], 409);
        }
        throw $e;
      }

      json_response(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }

    if ($action === 'update' && $method === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      $id = (int)($input['id'] ?? 0);
      $subject_name = trim($input['subject_name'] ?? '');
      $year_level_id = (int)($input['year_level_id'] ?? 0);
      $semester = $input['semester'] ?? 'Full Year';

      if ($id <= 0 || $subject_name === '' || $year_level_id <= 0 || !in_array($semester, ['1','2','Full Year'], true)) {
        json_response(['ok' => false, 'error' => 'Invalid input.'], 400);
      }

      $stmt = $pdo->prepare("
        UPDATE tbl_subjects
        SET subject_name = :name, year_level_id = :level, semester = :sem
        WHERE id = :id
      ");
      try {
        $stmt->execute([
          ':name' => $subject_name,
          ':level' => $year_level_id,
          ':sem' => $semester,
          ':id' => $id
        ]);
      } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
          json_response(['ok' => false, 'error' => 'Duplicate: subject_name + year_level_id must be unique.'], 409);
        }
        throw $e;
      }

      json_response(['ok' => true]);
    }

    if ($action === 'archive' && $method === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      $id = (int)($input['id'] ?? 0);
      if ($id <= 0) json_response(['ok' => false, 'error' => 'Invalid id.'], 400);

      $stmt = $pdo->prepare("UPDATE tbl_subjects SET archived = 1 WHERE id = :id");
      $stmt->execute([':id' => $id]);
      json_response(['ok' => true]);
    }

    if ($action === 'restore' && $method === 'POST') {
      $input = json_decode(file_get_contents('php://input'), true);
      $id = (int)($input['id'] ?? 0);
      if ($id <= 0) json_response(['ok' => false, 'error' => 'Invalid id.'], 400);

      $stmt = $pdo->prepare("UPDATE tbl_subjects SET archived = 0 WHERE id = :id");
      $stmt->execute([':id' => $id]);
      json_response(['ok' => true]);
    }

    // Unknown
    json_response(['ok' => false, 'error' => 'Unknown action or method.'], 400);

  } catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Subjects Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body { padding: 24px; }
    .pointer { cursor: pointer; }
    .badge-arch { background: #6c757d; }
    .table thead th { white-space: nowrap; }
    .form-select, .form-control { min-width: 180px; }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 m-0">Subjects Manager</h1>
      <div class="d-flex gap-2">
        <div class="form-check form-switch me-3">
          <input class="form-check-input" type="checkbox" role="switch" id="toggleArchived">
          <label class="form-check-label" for="toggleArchived">Show archived</label>
        </div>
        <button class="btn btn-primary" id="btnAdd"><span class="me-1">＋</span>Add Subject</button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle" id="tbl">
        <thead class="table-light">
          <tr>
            <th style="width:60px;">ID</th>
            <th>Subject Name</th>
            <th>Year Level</th>
            <th>Semester</th>
            <th style="width:200px;">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>

    <div id="alertBox" class="alert d-none" role="alert"></div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="subjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="subjectForm">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add Subject</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="id">
          <div class="mb-3">
            <label class="form-label">Subject Name</label>
            <input type="text" class="form-control" id="subject_name" maxlength="120" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Year Level</label>
            <select class="form-select" id="year_level_id" required>
              <option value="">— Select —</option>
              <option value="1">1 — Grade 1</option>
              <option value="2">2 — Grade 2</option>
              <option value="3">3 — Grade 3</option>
              <option value="4">4 — Grade 4</option>
              <option value="5">5 — Grade 5</option>
              <option value="6">6 — Grade 6</option>
              <option value="7">7 — Grade 7</option>
              <option value="8">8 — Grade 8</option>
              <option value="9">9 — Grade 9</option>
              <option value="10">10 — Grade 10</option>
              <option value="11">11 — Grade 11 (SHS)</option>
              <option value="12">12 — Grade 12 (SHS)</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Semester</label>
            <select class="form-select" id="semester" required>
              <option value="Full Year">Full Year</option>
              <option value="1">1</option>
              <option value="2">2</option>
            </select>
            <div class="form-text">
              Use <strong>Full Year</strong> for Grades 1–10; <strong>1</strong>/<strong>2</strong> for SHS.
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit" id="submitBtn">Save</button>
        </div>
      </form>
    </div>
  </div>

  <template id="rowActions">
    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-primary btn-edit">Edit</button>
      <button class="btn btn-sm btn-outline-danger btn-archive">Archive</button>
      <button class="btn btn-sm btn-outline-success btn-restore d-none">Restore</button>
    </div>
  </template>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const modalEl = document.getElementById('subjectModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('subjectForm');
    const tblBody = document.querySelector('#tbl tbody');
    const alertBox = document.getElementById('alertBox');
    const btnAdd = document.getElementById('btnAdd');
    const toggleArchived = document.getElementById('toggleArchived');

    let editingId = null;

    function showAlert(msg, type = 'success') {
      alertBox.className = 'alert alert-' + type;
      alertBox.textContent = msg;
      alertBox.classList.remove('d-none');
      setTimeout(() => alertBox.classList.add('d-none'), 3000);
    }

    function clearForm() {
      editingId = null;
      document.getElementById('id').value = '';
      document.getElementById('subject_name').value = '';
      document.getElementById('year_level_id').value = '';
      document.getElementById('semester').value = 'Full Year';
      document.getElementById('modalTitle').textContent = 'Add Subject';
    }

    function renderRows(data) {
      tblBody.innerHTML = '';
      const showArch = toggleArchived.checked;
      data.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${row.id}</td>
          <td>${row.subject_name}${row.archived ? ' <span class="badge badge-arch ms-2">Archived</span>' : ''}</td>
          <td>${row.year_level_id}</td>
          <td>${row.semester}</td>
          <td></td>
        `;
        const cell = tr.querySelector('td:last-child');
        const tpl = document.getElementById('rowActions').content.cloneNode(true);
        const btnEdit = tpl.querySelector('.btn-edit');
        const btnArchive = tpl.querySelector('.btn-archive');
        const btnRestore = tpl.querySelector('.btn-restore');

        if (row.archived) {
          btnArchive.classList.add('d-none');
          btnRestore.classList.remove('d-none');
        }

        btnEdit.addEventListener('click', () => {
          editingId = row.id;
          document.getElementById('modalTitle').textContent = 'Edit Subject';
          document.getElementById('id').value = row.id;
          document.getElementById('subject_name').value = row.subject_name;
          document.getElementById('year_level_id').value = row.year_level_id;
          document.getElementById('semester').value = row.semester;
          modal.show();
        });

        btnArchive.addEventListener('click', async () => {
          if (!confirm('Archive this subject?')) return;
          const res = await fetch('?action=archive', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: row.id })
          });
          const json = await res.json();
          if (json.ok) {
            showAlert('Subject archived.');
            loadData();
          } else {
            showAlert(json.error || 'Failed to archive.', 'danger');
          }
        });

        btnRestore.addEventListener('click', async () => {
          if (!confirm('Restore this subject?')) return;
          const res = await fetch('?action=restore', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: row.id })
          });
          const json = await res.json();
          if (json.ok) {
            showAlert('Subject restored.');
            loadData();
          } else {
            showAlert(json.error || 'Failed to restore.', 'danger');
          }
        });

        cell.appendChild(tpl);
        tblBody.appendChild(tr);
      });
    }

    async function loadData() {
      const res = await fetch(`?action=list&archived=${toggleArchived.checked ? 1 : 0}`);
      const json = await res.json();
      if (json.ok) {
        renderRows(json.data);
      } else {
        showAlert(json.error || 'Failed to load data.', 'danger');
      }
    }

    btnAdd.addEventListener('click', () => {
      clearForm();
      modal.show();
    });

    toggleArchived.addEventListener('change', loadData);

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        id: document.getElementById('id').value || undefined,
        subject_name: document.getElementById('subject_name').value.trim(),
        year_level_id: parseInt(document.getElementById('year_level_id').value, 10),
        semester: document.getElementById('semester').value
      };
      const isEdit = !!editingId;

      const res = await fetch(`?action=${isEdit ? 'update' : 'create'}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (json.ok) {
        modal.hide();
        showAlert(isEdit ? 'Subject updated.' : 'Subject added.');
        loadData();
      } else {
        showAlert(json.error || 'Operation failed.', 'danger');
      }
    });

    // initial
    loadData();
  </script>
</body>
</html>