<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
/**
 * The legal notice of the plugin ({@see \AppConnect\Attribution::html()}).
 *
 * @var string $style font size and colour of the place
 * @var string $text author, copyright and license
 * @var string $terms conveying and warranty
 * @var string $license_url
 * @var string $license_label
 * @var string $link_icon rendered icon markup, goes in as it is
 */
?>
<div class="app-connect-attribution" style="<?= htmlReady($style) ?>"><?= htmlReady($text) ?><br><?= htmlReady($terms) ?><br><a href="<?= htmlReady($license_url) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; white-space: nowrap;"><?= $link_icon ?><?= htmlReady($license_label) ?></a></div>
