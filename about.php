<?php if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = $_POST['rating'] ?? '';
    $feedback = trim($_POST['feedback'] ?? '');
    if ($rating && $feedback) {
        file_put_contents('data/about.txt', "★{$rating}: {$feedback}\n", FILE_APPEND);
    }
    header('Location: /dashboard.php');
    exit;
} ?>
<?php include 'includes/header.php'; ?>
<style>
form { max-width: 400px; margin: 2rem auto; text-align: center; }
.rating { direction: rtl; unicode-bidi: bidi-override; }
.rating input { display: none; }
.rating label { font-size: 2rem; color: #ccc; cursor: pointer; }
.rating input:checked ~ label,
.rating label:hover,
.rating label:hover ~ label { color: #f5b301; }
textarea { width: 100%; height: 100px; margin: 1rem 0; padding: 0.5rem; }
button { padding: 0.5rem 1rem; }
</style>
<form method="post">
  <div class="rating">
    <input type="radio" name="rating" value="5" id="r5"><label for="r5" class="fa fa-star"></label>
    <input type="radio" name="rating" value="4" id="r4"><label for="r4" class="fa fa-star"></label>
    <input type="radio" name="rating" value="3" id="r3"><label for="r3" class="fa fa-star"></label>
    <input type="radio" name="rating" value="2" id="r2"><label for="r2" class="fa fa-star"></label>
    <input type="radio" name="rating" value="1" id="r1"><label for="r1" class="fa fa-star"></label>
  </div>
  <textarea name="feedback" placeholder="Що б ви хотіли додати, прибрати, змінити? Якісь нові питання, категорії, функції? ..."></textarea>
  <button type="submit">Відправити</button>
</form>
<?php include 'includes/footer.php'; ?>
