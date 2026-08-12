<?php
/**
 * gan.php — A **pure PHP** Generative Adversarial Network for short
 * pollution-level time-series.
 *
 *   ┌─────────────────────────────────────────────────────────────────┐
 *   │ Architecture (Goodfellow et al. 2014, adapted to 1-D series)    │
 *   ├─────────────────────────────────────────────────────────────────┤
 *   │ Generator G(z) :                                                 │
 *   │   z ∈ R^{LATENT}                                                 │
 *   │   h1 = LeakyReLU(W1·z + b1)            (hidden 24)               │
 *   │   y  = tanh(W2·h1 + b2)                (sequence length 24)      │
 *   │                                                                  │
 *   │ Discriminator D(x) :                                             │
 *   │   x ∈ R^{SEQ_LEN}                                                │
 *   │   h1 = LeakyReLU(W1·x + b1)            (hidden 24)               │
 *   │   p  = sigmoid(W2·h1 + b2)             (scalar in [0,1])         │
 *   │                                                                  │
 *   │ Training (mini-batch SGD with momentum)                          │
 *   │   loss_D = − E[log D(x_real)] − E[log(1 − D(G(z)))]              │
 *   │   loss_G = − E[log D(G(z))]                                      │
 *   │                                                                  │
 *   │ Backpropagation is implemented by hand using the chain rule.     │
 *   │ No PDL / FFI / Python — just numerical loops over flat arrays.   │
 *   └─────────────────────────────────────────────────────────────────┘
 *
 * Why pure PHP?
 *  - Runs out-of-the-box inside WAMP — no Python, no TensorFlow.
 *  - 100 % deterministic (seedable with `mt_srand`) so the demo for the
 *    jury is reproducible.
 *  - Small enough (300 epochs × batch 8 × seq_len 24 ≈ 1 min on a laptop).
 *
 * Output : the learned weights are saved as JSON to a file.  The
 * generated synthetic series can then be inserted into the
 * `risk_scores_augmented` table (see scripts/gan_generate.php).
 *
 * References for the mémoire
 *  - Goodfellow et al., "Generative Adversarial Nets", NeurIPS 2014
 *  - Yoon et al., "Time-series Generative Adversarial Networks (TimeGAN)",
 *    NeurIPS 2019 — same idea, with a recurrent backbone we approximate
 *    with a windowed MLP for PHP speed.
 */
declare(strict_types=1);

const GAN_LATENT  = 8;
const GAN_HIDDEN  = 24;
const GAN_SEQ_LEN = 24;     // 24 hourly samples → daily window

/* ────────────────────────────── helpers ────────────────────────────── */

function gan_seed(?int $s = null): void
{
    mt_srand($s ?? 1337);
}

function gan_randn(): float
{
    // Box–Muller, returns a sample from N(0,1)
    $u = (mt_rand() + 1) / (mt_getrandmax() + 2);
    $v = (mt_rand() + 1) / (mt_getrandmax() + 2);
    return sqrt(-2.0 * log($u)) * cos(2.0 * M_PI * $v);
}

function gan_vec_zero(int $n): array
{
    return array_fill(0, $n, 0.0);
}

function gan_mat_init(int $rows, int $cols, float $scale = 0.0): array
{
    if ($scale === 0.0) {
        // Xavier/Glorot initialisation
        $scale = sqrt(2.0 / ($rows + $cols));
    }
    $m = [];
    for ($i = 0; $i < $rows; $i++) {
        $row = [];
        for ($j = 0; $j < $cols; $j++) $row[] = gan_randn() * $scale;
        $m[] = $row;
    }
    return $m;
}

/** y = W·x + b */
function gan_linear(array $W, array $b, array $x): array
{
    $rows = count($W);
    $cols = count($x);
    $out  = $b;
    for ($i = 0; $i < $rows; $i++) {
        $Wi = $W[$i];
        $s  = $b[$i];
        for ($j = 0; $j < $cols; $j++) $s += $Wi[$j] * $x[$j];
        $out[$i] = $s;
    }
    return $out;
}

function gan_leaky_relu(array $v, float $a = 0.2): array
{
    $out = [];
    foreach ($v as $x) $out[] = $x > 0 ? $x : $a * $x;
    return $out;
}

function gan_leaky_relu_grad(array $pre, float $a = 0.2): array
{
    $out = [];
    foreach ($pre as $x) $out[] = $x > 0 ? 1.0 : $a;
    return $out;
}

function gan_tanh(array $v): array
{
    $out = [];
    foreach ($v as $x) $out[] = tanh($x);
    return $out;
}

function gan_tanh_grad_from_output(array $y): array
{
    $out = [];
    foreach ($y as $yi) $out[] = 1.0 - $yi * $yi;
    return $out;
}

function gan_sigmoid(float $x): float
{
    if ($x >= 0) {
        $z = exp(-$x);
        return 1.0 / (1.0 + $z);
    }
    $z = exp($x);
    return $z / (1.0 + $z);
}

/* ────────────────────────────── network init ──────────────────────── */

function gan_init_generator(): array
{
    return [
        'W1' => gan_mat_init(GAN_HIDDEN, GAN_LATENT),
        'b1' => gan_vec_zero(GAN_HIDDEN),
        'W2' => gan_mat_init(GAN_SEQ_LEN, GAN_HIDDEN),
        'b2' => gan_vec_zero(GAN_SEQ_LEN),
        // SGD-momentum buffers
        'mW1' => gan_mat_init(GAN_HIDDEN, GAN_LATENT, 1e-12),
        'mb1' => gan_vec_zero(GAN_HIDDEN),
        'mW2' => gan_mat_init(GAN_SEQ_LEN, GAN_HIDDEN, 1e-12),
        'mb2' => gan_vec_zero(GAN_SEQ_LEN),
    ];
}

function gan_init_discriminator(): array
{
    return [
        'W1' => gan_mat_init(GAN_HIDDEN, GAN_SEQ_LEN),
        'b1' => gan_vec_zero(GAN_HIDDEN),
        'W2' => gan_mat_init(1, GAN_HIDDEN),
        'b2' => gan_vec_zero(1),
        'mW1' => gan_mat_init(GAN_HIDDEN, GAN_SEQ_LEN, 1e-12),
        'mb1' => gan_vec_zero(GAN_HIDDEN),
        'mW2' => gan_mat_init(1, GAN_HIDDEN, 1e-12),
        'mb2' => gan_vec_zero(1),
    ];
}

/* ────────────────────────────── forward passes ────────────────────── */

/** Returns cache  ['z','h1_pre','h1','y'] */
function gan_g_forward(array $G, array $z): array
{
    $h1_pre = gan_linear($G['W1'], $G['b1'], $z);
    $h1     = gan_leaky_relu($h1_pre);
    $y_pre  = gan_linear($G['W2'], $G['b2'], $h1);
    $y      = gan_tanh($y_pre);
    return ['z' => $z, 'h1_pre' => $h1_pre, 'h1' => $h1, 'y_pre' => $y_pre, 'y' => $y];
}

/** Returns cache ['x','h1_pre','h1','p_pre','p'] */
function gan_d_forward(array $D, array $x): array
{
    $h1_pre = gan_linear($D['W1'], $D['b1'], $x);
    $h1     = gan_leaky_relu($h1_pre);
    $p_pre  = gan_linear($D['W2'], $D['b2'], $h1);
    $p      = gan_sigmoid($p_pre[0]);
    return ['x' => $x, 'h1_pre' => $h1_pre, 'h1' => $h1, 'p_pre' => $p_pre[0], 'p' => $p];
}

/* ────────────────────────────── parameter update ───────────────────── */

function gan_sgd_apply(array &$net, array $grads, float $lr, float $mom): void
{
    foreach (['W1', 'W2'] as $k) {
        $rows = count($net[$k]);
        $cols = count($net[$k][0]);
        $g    = $grads[$k];
        $m    = $net['m' . $k];
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $m[$i][$j] = $mom * $m[$i][$j] - $lr * $g[$i][$j];
                $net[$k][$i][$j] += $m[$i][$j];
            }
        }
        $net['m' . $k] = $m;
    }
    foreach (['b1', 'b2'] as $k) {
        $n = count($net[$k]);
        $g = $grads[$k];
        $m = $net['m' . $k];
        for ($i = 0; $i < $n; $i++) {
            $m[$i] = $mom * $m[$i] - $lr * $g[$i];
            $net[$k][$i] += $m[$i];
        }
        $net['m' . $k] = $m;
    }
}

/* ────────────────────────────── backward passes ───────────────────── */

/**
 * Compute gradients of the binary-cross-entropy loss
 *   L = -[ y log p + (1-y) log (1-p) ]
 * with respect to the D parameters AND with respect to its input x
 * (the latter is needed when D was applied to G(z) to backprop further
 * into G).  Returns:
 *   ['grads' => same shape as D, 'dx' => array(seq_len)]
 */
function gan_d_backward(array $D, array $cache, float $target): array
{
    // dL/dz = p - target  (z = pre-sigmoid logit)
    $dp_pre = $cache['p'] - $target;
    $h1     = $cache['h1'];
    $h1_pre = $cache['h1_pre'];

    // dL/dW2 = dp_pre * h1 ;  dL/db2 = dp_pre
    $W2 = $D['W2'][0];
    $gW2 = [array_map(fn($v) => $dp_pre * $v, $h1)];
    $gb2 = [$dp_pre];

    // dL/dh1  (scalar dp_pre × W2 row)
    $dh1 = [];
    for ($i = 0, $n = count($W2); $i < $n; $i++) {
        $dh1[] = $dp_pre * $W2[$i];
    }

    // through LeakyReLU
    $relu_grad = gan_leaky_relu_grad($h1_pre);
    $dh1_pre = [];
    foreach ($dh1 as $i => $v) $dh1_pre[] = $v * $relu_grad[$i];

    // dL/dW1 = outer(dh1_pre, x) ;  dL/db1 = dh1_pre
    $x  = $cache['x'];
    $W1 = $D['W1'];
    $gW1 = [];
    for ($i = 0, $rows = count($W1); $i < $rows; $i++) {
        $row = [];
        $d = $dh1_pre[$i];
        for ($j = 0, $cols = count($W1[0]); $j < $cols; $j++) {
            $row[] = $d * $x[$j];
        }
        $gW1[] = $row;
    }
    $gb1 = $dh1_pre;

    // dL/dx (column-product through W1)
    $dx = gan_vec_zero(count($W1[0]));
    for ($i = 0, $rows = count($W1); $i < $rows; $i++) {
        $d = $dh1_pre[$i];
        $row = $W1[$i];
        for ($j = 0, $cols = count($row); $j < $cols; $j++) {
            $dx[$j] += $d * $row[$j];
        }
    }

    return [
        'grads' => ['W1' => $gW1, 'b1' => $gb1, 'W2' => $gW2, 'b2' => $gb2],
        'dx'    => $dx,
    ];
}

/**
 * Backprop through G using the upstream gradient dy that comes from D
 * (see gan_d_backward()['dx']).  Returns gradients only — the caller
 * applies SGD.
 */
function gan_g_backward(array $G, array $cache, array $dy): array
{
    // through tanh
    $tanh_g = gan_tanh_grad_from_output($cache['y']);
    $dy_pre = [];
    foreach ($dy as $i => $v) $dy_pre[] = $v * $tanh_g[$i];

    // dL/dW2 = outer(dy_pre, h1) ;  dL/db2 = dy_pre
    $W2 = $G['W2'];
    $h1 = $cache['h1'];
    $h1_pre = $cache['h1_pre'];
    $rows = count($W2); $cols = count($W2[0]);
    $gW2 = [];
    for ($i = 0; $i < $rows; $i++) {
        $row = []; $d = $dy_pre[$i];
        for ($j = 0; $j < $cols; $j++) $row[] = $d * $h1[$j];
        $gW2[] = $row;
    }
    $gb2 = $dy_pre;

    // dL/dh1 = W2^T · dy_pre
    $dh1 = gan_vec_zero($cols);
    for ($i = 0; $i < $rows; $i++) {
        $row = $W2[$i]; $d = $dy_pre[$i];
        for ($j = 0; $j < $cols; $j++) $dh1[$j] += $d * $row[$j];
    }

    // through LeakyReLU
    $relu_grad = gan_leaky_relu_grad($h1_pre);
    $dh1_pre = [];
    foreach ($dh1 as $i => $v) $dh1_pre[] = $v * $relu_grad[$i];

    // dL/dW1 = outer(dh1_pre, z) ;  dL/db1 = dh1_pre
    $z = $cache['z'];
    $W1 = $G['W1'];
    $rows1 = count($W1); $cols1 = count($W1[0]);
    $gW1 = [];
    for ($i = 0; $i < $rows1; $i++) {
        $row = []; $d = $dh1_pre[$i];
        for ($j = 0; $j < $cols1; $j++) $row[] = $d * $z[$j];
        $gW1[] = $row;
    }
    $gb1 = $dh1_pre;

    return ['W1' => $gW1, 'b1' => $gb1, 'W2' => $gW2, 'b2' => $gb2];
}

/* ────────────────────────────── batch helpers ─────────────────────── */

function gan_grad_zeros_like(array $net): array
{
    $z = [
        'W1' => gan_mat_init(count($net['W1']), count($net['W1'][0]), 1e-12),
        'b1' => gan_vec_zero(count($net['b1'])),
        'W2' => gan_mat_init(count($net['W2']), count($net['W2'][0]), 1e-12),
        'b2' => gan_vec_zero(count($net['b2'])),
    ];
    // reset to 0
    foreach (['W1', 'W2'] as $k) {
        $rows = count($z[$k]);
        $cols = count($z[$k][0]);
        for ($i = 0; $i < $rows; $i++)
            for ($j = 0; $j < $cols; $j++) $z[$k][$i][$j] = 0.0;
    }
    return $z;
}

function gan_grad_accum(array &$acc, array $g, float $w = 1.0): void
{
    foreach (['W1', 'W2'] as $k) {
        $rows = count($g[$k]); $cols = count($g[$k][0]);
        for ($i = 0; $i < $rows; $i++)
            for ($j = 0; $j < $cols; $j++) $acc[$k][$i][$j] += $w * $g[$k][$i][$j];
    }
    foreach (['b1', 'b2'] as $k) {
        $n = count($g[$k]);
        for ($i = 0; $i < $n; $i++) $acc[$k][$i] += $w * $g[$k][$i];
    }
}

/* ────────────────────────────── training loop ─────────────────────── */

/**
 * Train the GAN on a set of REAL windows.
 *
 * @param float[][] $realWindows  array of seq_len arrays in [-1, 1]
 * @param array $opts             ['epochs','batch','lr','momentum','verbose']
 * @return array                  ['G' => ..., 'D' => ..., 'history' => [...]]
 */
function gan_train(array $realWindows, array $opts = []): array
{
    $epochs = (int)($opts['epochs']   ?? 300);
    $batch  = (int)($opts['batch']    ?? 8);
    $lr     = (float)($opts['lr']     ?? 0.001);
    $mom    = (float)($opts['momentum'] ?? 0.9);
    $verb   = (bool)($opts['verbose'] ?? false);

    gan_seed((int)($opts['seed'] ?? 1337));
    $G = gan_init_generator();
    $D = gan_init_discriminator();
    $history = [];

    $N = count($realWindows);
    if ($N === 0) {
        return ['G' => $G, 'D' => $D, 'history' => [], 'error' => 'empty_dataset'];
    }

    for ($e = 0; $e < $epochs; $e++) {
        $idx = range(0, $N - 1);
        shuffle($idx);

        $accD_loss = 0.0; $accG_loss = 0.0; $steps = 0;
        for ($k = 0; $k < $N; $k += $batch) {
            $end = min($N, $k + $batch);
            $bn  = $end - $k;
            $w   = 1.0 / $bn;

            /* ── D step ─────────────────────────────────────────── */
            $gD = gan_grad_zeros_like($D);
            for ($i = $k; $i < $end; $i++) {
                $real = $realWindows[$idx[$i]];
                $cR   = gan_d_forward($D, $real);
                $bR   = gan_d_backward($D, $cR, 1.0);
                gan_grad_accum($gD, $bR['grads'], $w);
                $accD_loss += -log(max(1e-8, $cR['p']));

                $zn   = []; for ($j = 0; $j < GAN_LATENT; $j++) $zn[] = gan_randn();
                $cG   = gan_g_forward($G, $zn);
                $cFak = gan_d_forward($D, $cG['y']);
                $bF   = gan_d_backward($D, $cFak, 0.0);
                gan_grad_accum($gD, $bF['grads'], $w);
                $accD_loss += -log(max(1e-8, 1.0 - $cFak['p']));
            }
            gan_sgd_apply($D, $gD, $lr, $mom);

            /* ── G step (fool the freshly-updated D) ───────────── */
            $gG = gan_grad_zeros_like($G);
            for ($i = 0; $i < $bn; $i++) {
                $zn = []; for ($j = 0; $j < GAN_LATENT; $j++) $zn[] = gan_randn();
                $cG = gan_g_forward($G, $zn);
                $cFak = gan_d_forward($D, $cG['y']);
                // generator wants D(G(z)) → 1 ; binary-cross-entropy with target=1
                $bF = gan_d_backward($D, $cFak, 1.0);
                $dy = $bF['dx'];
                $gGi = gan_g_backward($G, $cG, $dy);
                gan_grad_accum($gG, $gGi, $w);
                $accG_loss += -log(max(1e-8, $cFak['p']));
            }
            gan_sgd_apply($G, $gG, $lr, $mom);
            $steps++;
        }

        $dL = $accD_loss / max(1, $steps * 2 * $batch);
        $gL = $accG_loss / max(1, $steps * $batch);
        $history[] = ['epoch' => $e + 1, 'loss_D' => $dL, 'loss_G' => $gL];

        if ($verb && ($e === 0 || ($e + 1) % 50 === 0 || $e === $epochs - 1)) {
            printf("epoch %4d   loss_D=%.4f   loss_G=%.4f\n", $e + 1, $dL, $gL);
        }
    }

    return ['G' => $G, 'D' => $D, 'history' => $history];
}

/* ────────────────────────────── sampling ─────────────────────────── */

/**
 * Generate `$n` synthetic windows from a trained Generator.
 * Returns an array of seq_len arrays in [-1, 1].
 */
function gan_sample(array $G, int $n): array
{
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $z = [];
        for ($j = 0; $j < GAN_LATENT; $j++) $z[] = gan_randn();
        $c = gan_g_forward($G, $z);
        $out[] = $c['y'];
    }
    return $out;
}

/* ────────────────────────────── save / load ─────────────────────── */

function gan_save_weights(string $path, array $G, array $D, array $meta = []): void
{
    $strip = function (array $net): array {
        // Remove momentum buffers; keep only the trainable weights for portability.
        return [
            'W1' => $net['W1'], 'b1' => $net['b1'],
            'W2' => $net['W2'], 'b2' => $net['b2'],
        ];
    };
    $payload = [
        'version'     => 1,
        'arch'        => [
            'latent' => GAN_LATENT, 'hidden' => GAN_HIDDEN, 'seq_len' => GAN_SEQ_LEN,
        ],
        'meta'        => $meta,
        'generator'   => $strip($G),
        'discriminator' => $strip($D),
        'saved_at'    => date('c'),
    ];
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT));
}

function gan_load_weights(string $path): array
{
    $raw = (string)@file_get_contents($path);
    if ($raw === '') throw new RuntimeException("Empty or unreadable weights file: $path");
    $j = json_decode($raw, true);
    if (!is_array($j)) throw new RuntimeException("Invalid GAN weights JSON: $path");
    $reset_mom = function (array $net): array {
        $net['mW1'] = gan_mat_init(count($net['W1']), count($net['W1'][0]), 1e-12);
        $net['mb1'] = gan_vec_zero(count($net['b1']));
        $net['mW2'] = gan_mat_init(count($net['W2']), count($net['W2'][0]), 1e-12);
        $net['mb2'] = gan_vec_zero(count($net['b2']));
        // reset to 0
        foreach (['mW1', 'mW2'] as $k) {
            $rows = count($net[$k]);
            $cols = count($net[$k][0]);
            for ($i = 0; $i < $rows; $i++)
                for ($jj = 0; $jj < $cols; $jj++) $net[$k][$i][$jj] = 0.0;
        }
        return $net;
    };
    return [
        'G' => $reset_mom($j['generator']),
        'D' => $reset_mom($j['discriminator']),
        'meta' => $j['meta'] ?? [],
    ];
}

/* ────────────────────────────── normalization ─────────────────────── */

/** Map a pollution score in [0,100] to [-1,1]. */
function gan_norm(float $score): float
{
    return max(-1.0, min(1.0, ($score - 50.0) / 50.0));
}

/** Map back from [-1,1] to a clipped int in [0,100]. */
function gan_denorm(float $x): int
{
    $v = (int)round(50.0 + 50.0 * $x);
    return max(0, min(100, $v));
}
