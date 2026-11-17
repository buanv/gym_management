
<?php
session_start();

require_once __DIR__ . '/config/db.php';

// Only logged-in Members can access this page.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'member'
) {
    header('Location: login.php');
    exit;
}

$memberId = (int) $_SESSION['user_id'];

function escape($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Create a security token.
if (empty($_SESSION['weight_csrf'])) {
    $_SESSION['weight_csrf'] = bin2hex(random_bytes(32));
}

// Retrieve weight history for this member.
$stmt = $conn->prepare(
    "SELECT id, weight_kg, recorded_at
     FROM weight_records
     WHERE member_id = ?
     ORDER BY recorded_at DESC, id DESC"
);

$stmt->bind_param('i', $memberId);
$stmt->execute();

$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// Prepare weight records in chronological order.
$chartRecords = array_reverse($records);

$chartLabels = [];
$chartWeights = [];

foreach ($chartRecords as $record) {
    $chartLabels[] = date(
        'M d, Y H:i',
        strtotime($record['recorded_at'])
    );

    $chartWeights[] = (float) $record['weight_kg'];
}


$currentWeight = null;
$startingWeight = null;
$weightChange = null;

if (count($records) > 0) {

    $currentWeight = (float) $records[0]['weight_kg'];

    $lastRecord = $records[count($records) - 1];
    $startingWeight = (float) $lastRecord['weight_kg'];

    $weightChange = $currentWeight - $startingWeight;
}

$message = $_SESSION['weight_message'] ?? '';
unset($_SESSION['weight_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Weight Tracker | Gym Management</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #111827;
    color: white;
    font-family: Arial, sans-serif;
}

header {
    background: #1f2937;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header h2 {
    color: #22c55e;
    margin: 0;
}

header a {
    color: #22c55e;
    text-decoration: none;
}

.container {
    max-width: 1100px;
    margin: auto;
    padding: 35px 20px;
}

.subtitle {
    color: #9ca3af;
}

.card {
    background: #1f2937;
    border-radius: 12px;
    padding: 25px;
    margin-top: 25px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 25px;
}

.summary-card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
}

.summary-card h3 {
    color: #9ca3af;
    font-size: 16px;
}

.summary-number {
    color: #4ade80;
    font-size: 32px;
    font-weight: bold;
}

label {
    display: block;
    margin-bottom: 10px;
}

input[type="number"] {
    width: 100%;
    max-width: 350px;
    padding: 13px;
    background: #111827;
    color: white;
    border: 1px solid #374151;
    border-radius: 8px;
}

button {
    display: block;
    background: #22c55e;
    color: #111827;
    padding: 12px 25px;
    margin-top: 20px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #16a34a;
}

.table-box {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #374151;
}

th {
    color: #4ade80;
    background: #273449;
}

.message {
    background: #273449;
    color: #4ade80;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.empty {
    text-align: center;
    color: #9ca3af;
}

@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}

.chart-container {
    position: relative;
    width: 100%;
    height: 320px;
    margin-top: 20px;
}

.delete-btn {
    background: #dc2626;
    color: white;
    padding: 9px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    margin-top: 0;
}

.delete-btn:hover {
    background: #b91c1c;
}

.edit-btn {
    background: #2563eb;
    color: white;
    padding: 9px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 8px;
}

.edit-btn:hover {
    background: #1d4ed8;
}

.cancel-btn {
    background: #4b5563;
    color: white;
}

.cancel-btn:hover {
    background: #374151;
}


</style>
</head>

<body>

<header>
    <h2>4BROS AND 1BOSS</h2>
    <a href="member_dashboard.php">
        Back to Dashboard
    </a>
</header>

<div class="container">

    <h1>My Weight Tracker</h1>

    <p class="subtitle">
        Record your weight and monitor your progress.
    </p>

    <?php if ($message !== ''): ?>
        <div class="message">
            <?= escape($message) ?>
        </div>
    <?php endif; ?>

    <div class="summary-grid">

        <div class="summary-card">
            <h3>Starting Weight</h3>
            <p class="summary-number">
                <?= $startingWeight !== null
                    ? number_format($startingWeight, 2) . ' kg'
                    : '--' ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Current Weight</h3>
            <p class="summary-number">
                <?= $currentWeight !== null
                    ? number_format($currentWeight, 2) . ' kg'
                    : '--' ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Weight Change</h3>
            <p class="summary-number">
                <?= $weightChange !== null
                    ? number_format($weightChange, 2) . ' kg'
                    : '--' ?>
            </p>
        </div>

    </div>

    <div class="card">

        <h2>Record New Weight</h2>

        <form action="save_weight.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escape($_SESSION['weight_csrf']) ?>"
            >

            <label for="weight_kg">
                Current Weight (kg)
            </label>

            <input
                type="number"
                id="weight_kg"
                name="weight_kg"
                min="1"
                max="500"
                step="0.01"
                placeholder="Example: 65.50"
                required
            >

            <button type="submit">
                Save Weight
            </button>

        </form>

    </div>


<div class="card">

    <h2>My Weight Progress</h2>

    <p class="subtitle">
        View how your weight changes over time.
    </p>

    <?php if (count($records) > 0): ?>

        <div class="chart-container">
            <canvas id="weightChart"></canvas>
        </div>

    <?php else: ?>

        <p class="subtitle">
            Record your first weight to see your progress chart.
        </p>

    <?php endif; ?>

</div>

<div class="card" id="editWeightCard" style="display:none;">

    <h2>Edit Weight Record</h2>

    <form action="update_weight.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= escape($_SESSION['weight_csrf']) ?>"
        >

        <input
            type="hidden"
            name="record_id"
            id="editRecordId"
        >

        <label for="editWeightInput">
            Correct Weight (kg)
        </label>

        <input
            type="number"
            id="editWeightInput"
            name="weight_kg"
            min="1"
            max="500"
            step="0.01"
            required
        >

        <button type="submit">
            Save Changes
        </button>

        <button
            type="button"
            class="cancel-btn"
            onclick="cancelEdit()"
        >
            Cancel
        </button>

    </form>

</div>

    <div class="card">

        <h2>Weight History</h2>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <<th>Record ID</th>
                        <th>Weight (kg)</th>
                        <th>Date Recorded</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($records) > 0): ?>

                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <?= escape($record['id']) ?>
                            </td>

                            <td>
                                <?= number_format(
                                    (float)$record['weight_kg'],
                                    2
                                ) ?> kg
                            </td>

                            <td>
                                <?= escape($record['recorded_at']) ?>
                            </td>

                            <td>
                                <form
                                    action="delete_weight.php"
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this weight record?');"
                                >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escape($_SESSION['weight_csrf']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="record_id"
                                    value="<?= escape($record['id']) ?>"
                                    >

                                <button type="submit" class="delete-btn">
                                    Delete
                                </button>
                                </form>

                                <button
                                    type="button"
                                    class="edit-btn"
                                    onclick="editWeight(
                                    <?= (int) $record['id'] ?>,
                                     <?= (float) $record['weight_kg'] ?>)"
                                    >
                                    Edit
                                </button>
                            </td>
                        
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="4" class="empty">
                            No weight records yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>

<script>
const weightLabels = <?= json_encode(
    $chartLabels,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const weightValues = <?= json_encode(
    $chartWeights,
    JSON_NUMERIC_CHECK
) ?>;

const chartCanvas = document.getElementById('weightChart');

if (chartCanvas && typeof Chart !== 'undefined') {
    new Chart(chartCanvas, {
        type: 'line',
        data: {
            labels: weightLabels,
            datasets: [{
                label: 'Weight (kg)',
                data: weightValues,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,0.15)',
                borderWidth: 3,
                tension: 0.2,
                fill: true,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff'
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#9ca3af'
                    }
                },
                y: {
                    ticks: {
                        color: '#9ca3af'
                    },
                    title: {
                        display: true,
                        text: 'Weight (kg)',
                        color: '#ffffff'
                    }
                }
            }
        }
    });
}
</script>

<script>
function editWeight(recordId, weight) {
    const card = document.getElementById('editWeightCard');

    document.getElementById('editRecordId').value = recordId;
    document.getElementById('editWeightInput').value = weight;

    card.style.display = 'block';

    card.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
}

function cancelEdit() {
    document.getElementById('editWeightCard').style.display = 'none';
    document.getElementById('editRecordId').value = '';
    document.getElementById('editWeightInput').value = '';
}
</script>

</body>
</html>
