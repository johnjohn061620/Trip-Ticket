<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete' && $id > 0) {
        $stmt = db()->prepare('DELETE FROM trip_tickets WHERE id = ?');
        $stmt->execute([$id]);
        flash('Trip ticket deleted.');
        redirect('index.php');
    }

    if ($action === 'duplicate' && $id > 0) {
        $stmt = db()->prepare('SELECT * FROM trip_tickets WHERE id = ?');
        $stmt->execute([$id]);
        $t = $stmt->fetch();
        if ($t) {
            $cols = array_keys($t);
            $vals = array_values($t);
            $keep = ['ticket_no', 'ticket_date'];
            $insertCols = [];
            $insertVals = [];
            $placeholders = [];
            foreach ($cols as $i => $col) {
                if ($col === 'id' || $col === 'created_at' || $col === 'updated_at') {
                    continue;
                }
                $insertCols[] = $col;
                $placeholders[] = '?';
                $insertVals[] = in_array($col, $keep, true) ? '' : $vals[$i];
            }
            $sql = 'INSERT INTO trip_tickets (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $placeholders) . ')';
            db()->prepare($sql)->execute($insertVals);
            flash('Ticket duplicated - give it a new number.');
            redirect('ticket.php?id=' . (int) db()->lastInsertId());
        }
    }

    flash('Unknown action.', 'err');
    redirect('index.php');
}

[$whereSql, $params, $flt] = ticket_filter($_GET);
$q     = $flt['q'];
$month = $flt['month'];
$from  = $flt['from'];
$to    = $flt['to'];
$veh   = $flt['vehicle'];

$vehicleList = vehicle_list();
$vehicleNames = [];
$plateByName = [];
foreach ($vehicleList as $v) {
    $vehicleNames[] = $v['name'];
    $plateByName[$v['name']] = $v['plate'];
}

$months = db()->query(
    "SELECT DISTINCT DATE_FORMAT(date_iso, '%Y-%m') AS m FROM trip_tickets
     WHERE date_iso IS NOT NULL ORDER BY m DESC"
)->fetchAll(PDO::FETCH_COLUMN);
if ($month !== '' && !in_array($month, $months, true)) {
    $months[] = $month;
}

$perPage = 10;
$countStmt = db()->prepare('SELECT COUNT(*) FROM trip_tickets' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$curPage = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
$offset = ($curPage - 1) * $perPage;

// Sortable columns: URL key => [SQL column, sort expression]
$sortable = [
    'ticket_no' => ['col' => 'ticket_no', 'expr' => 'ticket_no'],
    'date'      => ['col' => 'date_iso',  'expr' => 'date_iso'],
    'driver'    => ['col' => 'driver_name', 'expr' => 'driver_name'],
    'vehicle'   => ['col' => 'vehicle_name', 'expr' => 'vehicle_name'],
    'purpose'   => ['col' => 'purpose',   'expr' => 'purpose'],
    'updated'   => ['col' => 'updated_at', 'expr' => 'updated_at'],
];
$sortKey = (string) ($_GET['sort'] ?? '');
if (!isset($sortable[$sortKey])) {
    $sortKey = ''; // default order
}
$sortDir = strtolower((string) ($_GET['dir'] ?? '')) === 'asc' ? 'asc' : 'desc';
if ($sortKey === '') {
    $orderBy = 'date_iso IS NULL, date_iso DESC, id DESC';
} elseif ($sortKey === 'date') {
    $orderBy = 'date_iso IS NULL, date_iso ' . $sortDir . ', id DESC';
} else {
    $orderBy = $sortable[$sortKey]['expr'] . ' ' . $sortDir . ', id DESC';
}

$stmt = db()->prepare(
    'SELECT id, ticket_no, ticket_date, driver_name, plate_no, vehicle_name, purpose, places, updated_at
       FROM trip_tickets' . $whereSql .
     ' ORDER BY ' . $orderBy .
     ' LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$filtersActive = ($q !== '' || $month !== '' || $from !== '' || $to !== '' || $veh !== '');
$baseQuery = [];
if ($q !== '') {
    $baseQuery['q'] = $q;
}
if ($month !== '') {
    $baseQuery['month'] = $month;
}
if ($from !== '') {
    $baseQuery['from'] = $from;
}
if ($to !== '') {
    $baseQuery['to'] = $to;
}
if ($veh !== '') {
    $baseQuery['vehicle'] = $veh;
}
if ($sortKey !== '') {
    $baseQuery['sort'] = $sortKey;
    $baseQuery['dir'] = $sortDir;
}
$linkBase = $baseQuery ? 'index.php?' . http_build_query($baseQuery) . '&' : 'index.php?';
$exportQuery = http_build_query($baseQuery);

// Build a sort-toggle link for a column header (keeps filters, resets to page 1)
$sortLink = function (string $key) use ($baseQuery, $sortKey, $sortDir): string {
    $dir = ($sortKey === $key && $sortDir === 'asc') ? 'desc' : 'asc';
    $qs = array_merge($baseQuery, ['sort' => $key, 'dir' => $dir]);
    return 'index.php?' . http_build_query($qs);
};
$sortArrow = function (string $key) use ($sortKey, $sortDir): string {
    if ($sortKey !== $key) {
        return '';
    }
    return $sortDir === 'asc' ? ' &#9650;' : ' &#9660;';
};

$page = 'home';
$page_title = 'Trip Tickets';
require __DIR__ . '/includes/header.php';
?>

<div class="toolbar">
  <div>
    <h1>Trip Tickets</h1>
    <div class="muted">
      <?php if ($total > 0): ?>
        Showing <?= $offset + 1 ?>&ndash;<?= $offset + count($tickets) ?> of <?= $total ?> ticket(s)
      <?php else: ?>
        0 tickets
      <?php endif; ?>
      <?= $filtersActive ? ' &middot; filtered' : '' ?>
    </div>
  </div>
  <div class="searchbar">
    <a class="btn" href="vehicle_pick.php">+ New Ticket</a>
    <a class="btn ghost" href="export_pdfs.php?<?= e($exportQuery . ($exportQuery ? '&' : '') . 'x=' . time()) ?>">Export PDFs (zip)</a>
    <a class="btn ghost" href="export.php?<?= e($exportQuery . ($exportQuery ? '&' : '') . 'format=csv') ?>">Export CSV</a>
    <a class="btn ghost" href="export.php?<?= e($exportQuery . ($exportQuery ? '&' : '') . 'format=print') ?>" target="_blank">Print backup</a>
  </div>
</div>

<div class="card filters">
  <form method="get" action="index.php">
    <div class="filter-grid">
      <div>
        <label>Search</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Number, driver, purpose...">
      </div>
      <div>
        <label>Month</label>
        <select name="month">
          <option value="">All months</option>
          <?php foreach ($months as $m): ?>
            <option value="<?= e($m) ?>" <?= $m === $month ? 'selected' : '' ?>><?= e(date('F Y', strtotime($m . '-01'))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>From date</label>
        <input type="date" name="from" value="<?= e($from) ?>">
      </div>
      <div>
        <label>To date</label>
        <input type="date" name="to" value="<?= e($to) ?>">
      </div>
      <div class="filter-actions">
        <button class="btn" type="submit">Apply</button>
        <?php if ($filtersActive): ?>
          <a class="btn ghost" href="index.php">Reset</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="vehicle-filter">
      <span class="vf-label">Vehicle:</span>
      <label><input type="radio" name="vehicle" value="" onchange="this.form.submit()" <?= $veh === '' ? 'checked' : '' ?>> All</label>
      <?php foreach ($vehicleNames as $vn): ?>
        <label>
          <input type="radio" name="vehicle" value="<?= e($vn) ?>" onchange="this.form.submit()" <?= $veh === $vn ? 'checked' : '' ?>>
          <?= e($vn) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </form>
</div>

<div class="card" style="padding:0;overflow-x:auto;">
<?php if (!$tickets): ?>
  <div class="empty">
    <?php if ($filtersActive): ?>
      <p>No tickets match these filters.</p>
      <a class="btn ghost" href="index.php">Clear filters</a>
    <?php else: ?>
      <p>No trip tickets yet.</p>
      <a class="btn" href="vehicle_pick.php">Create the first ticket</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <table class="list">
    <thead>
      <tr>
        <th><a class="sortable<?= $sortKey === 'ticket_no' ? ' active' : '' ?>" href="<?= e($sortLink('ticket_no')) ?>">Ticket No.<?= $sortArrow('ticket_no') ?></a></th>
        <th><a class="sortable<?= $sortKey === 'date' ? ' active' : '' ?>" href="<?= e($sortLink('date')) ?>">Date<?= $sortArrow('date') ?></a></th>
        <th><a class="sortable<?= $sortKey === 'driver' ? ' active' : '' ?>" href="<?= e($sortLink('driver')) ?>">Driver<?= $sortArrow('driver') ?></a></th>
        <th><a class="sortable<?= $sortKey === 'vehicle' ? ' active' : '' ?>" href="<?= e($sortLink('vehicle')) ?>">Vehicle<?= $sortArrow('vehicle') ?></a></th>
        <th><a class="sortable<?= $sortKey === 'purpose' ? ' active' : '' ?>" href="<?= e($sortLink('purpose')) ?>">Purpose<?= $sortArrow('purpose') ?></a></th>
        <th><a class="sortable<?= $sortKey === 'updated' ? ' active' : '' ?>" href="<?= e($sortLink('updated')) ?>">Updated<?= $sortArrow('updated') ?></a></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr>
        <td><a href="ticket.php?id=<?= (int) $t['id'] ?>"><b><?= e($t['ticket_no']) ?: '(no number)' ?></b></a></td>
        <td><?= e($t['ticket_date']) ?></td>
        <td><?= e($t['driver_name']) ?></td>
        <td>
          <?php if ($t['vehicle_name'] !== ''): ?>
            <?= e($t['vehicle_name']) ?><?= !empty($plateByName[$t['vehicle_name']]) ? ' (' . e($plateByName[$t['vehicle_name']]) . ')' : '' ?>
          <?php else: ?>
            <?= e($t['plate_no']) ?>
          <?php endif; ?>
        </td>
        <td><?= e($t['purpose']) ?></td>
        <td><?= e(date('M j, Y g:i A', strtotime($t['updated_at']))) ?></td>
        <td class="actions">
          <a class="btn sm ghost" href="ticket.php?id=<?= (int) $t['id'] ?>">Open</a>
          <form method="post" action="index.php">
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button class="btn sm ghost" type="submit" name="action" value="duplicate">Duplicate</button>
          </form>
          <form method="post" action="index.php" onsubmit="return confirm('Delete this trip ticket permanently?');">
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button class="btn sm danger" type="submit" name="action" value="delete">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php if ($curPage > 1): ?>
        <a class="btn sm ghost" href="<?= e($linkBase . 'page=' . ($curPage - 1)) ?>">&larr; Prev</a>
      <?php endif; ?>

      <?php
      $window = [];
      for ($p = max(1, $curPage - 2); $p <= min($pages, $curPage + 2); $p++) {
          $window[] = $p;
      }
      $first = $window[0];
      $last = $window[count($window) - 1];
      ?>
      <?php if ($first > 1): ?>
        <a class="page" href="<?= e($linkBase . 'page=1') ?>">1</a>
        <?php if ($first > 2): ?><span class="dots">&hellip;</span><?php endif; ?>
      <?php endif; ?>

      <?php foreach ($window as $p): ?>
        <?php if ($p === $curPage): ?>
          <b class="page current"><?= $p ?></b>
        <?php else: ?>
          <a class="page" href="<?= e($linkBase . 'page=' . $p) ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endforeach; ?>

      <?php if ($last < $pages): ?>
        <?php if ($last < $pages - 1): ?><span class="dots">&hellip;</span><?php endif; ?>
        <a class="page" href="<?= e($linkBase . 'page=' . $pages) ?>"><?= $pages ?></a>
      <?php endif; ?>

      <?php if ($curPage < $pages): ?>
        <a class="btn sm ghost" href="<?= e($linkBase . 'page=' . ($curPage + 1)) ?>">Next &rarr;</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
