<?php

declare(strict_types=1);

/**
 * Lecture de l'export WordPress (WXR) et nettoyage du contenu Elementor.
 * Outil partagé par les scripts de database/import/. Nécessite les extensions dom, simplexml et mbstring.
 */
final class WordpressExport
{
    /** Balises conservées dans le HTML nettoyé. Les autres sont retirées en gardant leur texte. */
    private const ALLOWED = ['p', 'ul', 'ol', 'li', 'strong', 'em', 'br', 'a', 'h3', 'h4'];
    private const RENAMED = ['b' => 'strong', 'i' => 'em', 'h1' => 'h3', 'h2' => 'h3', 'h5' => 'h4', 'h6' => 'h4'];
    private const REMOVED = ['hr', 'img', 'script', 'style', 'iframe', 'form', 'input', 'button'];

    /** @var array<string, array{title: string, elementor: array}> */
    private array $pages = [];

    public function __construct(string $file)
    {
        foreach (['dom', 'simplexml', 'mbstring'] as $extension) {
            if (!extension_loaded($extension)) {
                throw new RuntimeException("Extension PHP manquante : {$extension}");
            }
        }
        $xml = simplexml_load_file($file, options: LIBXML_NOCDATA | LIBXML_COMPACT);
        if ($xml === false) {
            throw new RuntimeException("Export illisible : {$file}");
        }
        $ns = $xml->getNamespaces(true);

        foreach ($xml->channel->item as $item) {
            $wp = $item->children($ns['wp']);
            $type = (string) $wp->post_type;
            if ($type === 'attachment') {
                $path = self::uploadPath((string) $wp->attachment_url);
                if ($path !== null) {
                    $this->attachments[(string) $wp->post_id] = $path;
                }
                continue;
            }
            if (!in_array($type, ['page', 'post'], true) || (string) $wp->status !== 'publish') {
                continue;
            }
            $elementor = [];
            $thumbnail = null;
            foreach ($wp->postmeta as $meta) {
                $key = (string) $meta->meta_key;
                if ($key === '_elementor_data') {
                    $elementor = json_decode((string) $meta->meta_value, true) ?: [];
                } elseif ($key === '_thumbnail_id') {
                    $thumbnail = (string) $meta->meta_value;
                }
            }
            $entry = ['title' => trim((string) $item->title), 'elementor' => $elementor];
            if ($type === 'page') {
                $this->pages[(string) $wp->post_name] = $entry;
                continue;
            }
            $categories = [];
            foreach ($item->category as $category) {
                if ((string) $category['domain'] === 'category') {
                    $categories[] = (string) $category['nicename'];
                }
            }
            $this->posts[(string) $wp->post_name] = $entry + [
                'id' => (int) $wp->post_id,
                'date' => (string) $wp->post_date,
                'categories' => $categories,
                'content' => (string) $item->children($ns['content'])->encoded,
                'excerpt' => trim((string) $item->children($ns['excerpt'])->encoded),
                'thumbnail' => $thumbnail,
            ];
        }
    }

    /** @var array<string, array> Articles publiés, par adresse. */
    private array $posts = [];

    /** @var array<string, string> Médias : identifiant WordPress => chemin sous wp-content/uploads. */
    private array $attachments = [];

    /** @return array{title: string, elementor: array}|null */
    public function page(string $slug): ?array
    {
        return $this->pages[$slug] ?? null;
    }

    /** @return array<string, array> Articles publiés : title, elementor, id, date, categories, content, excerpt, thumbnail. */
    public function posts(): array
    {
        return $this->posts;
    }

    /** Chemin du média WordPress d'identifiant donné, ou null. */
    public function attachment(?string $id): ?string
    {
        return $id !== null ? ($this->attachments[$id] ?? null) : null;
    }

    /** Liste à plat les widgets Elementor, dans l'ordre d'affichage. */
    public static function widgets(array $elements): array
    {
        $widgets = [];
        foreach ($elements as $element) {
            if (($element['elType'] ?? '') === 'widget') {
                $widgets[] = $element;
            }
            if (!empty($element['elements'])) {
                array_push($widgets, ...self::widgets($element['elements']));
            }
        }

        return $widgets;
    }

    /** « https://taysir-orientation.com/wp-content/uploads/2023/04/x.jpeg » → « 2023/04/x.jpeg ». */
    public static function uploadPath(string $url): ?string
    {
        return preg_match('#/wp-content/uploads/(.+)$#', $url, $m) ? rawurldecode($m[1]) : null;
    }

    /**
     * Casse française d'un titre : « Choix de l’Université et du Programme » → « Choix de l’université et du programme ».
     * Les noms propres sont à rétablir ensuite par l'appelant.
     */
    public static function sentenceCase(string $text): string
    {
        $text = mb_strtolower($text);

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    /** Nettoie le HTML d'un widget : balises autorisées, aucun attribut sauf href, paragraphes vides retirés. */
    public static function cleanHtml(string $html): string
    {
        $html = str_replace(["\r\n", "\u{a0}", '&nbsp;'], ["\n", ' ', ' '], $html);
        if (!preg_match('/<(p|ul|ol|div|h\d)\b/i', $html)) {
            // Texte brut d'Elementor : un saut de ligne double sépare deux paragraphes.
            $paragraphs = preg_split('/\n\s*\n/', trim($html));
            $html = implode('', array_map(static fn (string $p): string => '<p>' . nl2br(trim($p), false) . '</p>', $paragraphs));
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('root');
        self::cleanNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        // Texte nu restant au premier niveau (après suppression d'un div) : le mettre en paragraphe.
        $out = preg_replace('#^(?!<(p|ul|ol|h3|h4)\b)(.+?)(?=<(p|ul|ol|h3|h4)\b|$)#s', '<p>$2</p>', trim($out));
        $out = preg_replace('#(</(p|ul|ol|h3|h4)>)(\s*<br>)+#', '$1', $out);
        $out = preg_replace('#^(\s*<br>)+#', '', $out);
        // Deux sauts de ligne ou plus dans un paragraphe : deux paragraphes.
        $out = preg_replace_callback('#<p>.*?</p>#s', static fn (array $m): string => preg_replace('#(\s*<br>){2,}\s*#', '</p><p>', $m[0]), $out);
        $out = self::markdown($out);
        // Marqueurs de gras orphelins (ouverts sans être fermés dans le texte d'origine).
        $out = str_replace('**', '', $out);
        // Listes coupées en une liste par élément dans Elementor : une seule liste.
        $out = preg_replace('#</(ul|ol)><\1>#', '', $out);
        $out = str_replace('</strong><a ', '</strong> <a ', $out);
        // Paragraphe entièrement en gras : court et sans point final, c'est un sous-titre ;
        // long, c'est un paragraphe mis en gras par habitude dans Elementor.
        $out = preg_replace_callback('#<p><strong>((?:(?!</?strong>).)*)</strong>\s*:?\s*</p>#s', static function (array $m): string {
            $text = trim(strip_tags($m[1]));
            // Ni puce (« – », « 1- »), ni ponctuation de liste ou de phrase en fin : sinon c'est un élément de liste ou une phrase.
            $isHeading = mb_strlen($text) >= 4 && mb_strlen($text) <= 75 && !preg_match('/[.!?…;,]$/u', $text) && !preg_match('/^([–—•*-]|\d+\s*[-.)])/u', $text);

            return $isHeading ? '<h3>' . rtrim(trim($m[1]), ' :') . '</h3>' : '<p>' . $m[1] . '</p>';
        }, $out);
        // Paragraphe court et sans ponctuation finale juste avant une liste : c'est l'intitulé de la liste.
        $out = preg_replace_callback('#<p>([^<]{2,70})</p>(?=<(ul|ol)>)#u', static fn (array $m): string => preg_match('/[.!?…:,;]$/u', trim($m[1])) ? $m[0] : '<h3>' . trim($m[1]) . '</h3>', $out);
        $out = preg_replace('#<p>(\s|<br>)*</p>#', '', $out);
        $out = preg_replace('#(<br>\s*)+</(p|li)>#', '</$2>', $out);
        $out = preg_replace('#<(p|li)>(\s|<br>)+#', '<$1>', $out);
        $out = preg_replace('/[ \t]+/', ' ', $out);
        $out = preg_replace('#\s*(</?(p|ul|ol|li|h3|h4)>)\s*#', '$1', $out);

        return $out;
    }

    /**
     * Certains textes ont été collés dans Elementor en Markdown brut (« ### Titre », « - point », « **gras** »,
     * « [lien](https://…) ») et s'affichaient tels quels. Ils sont convertis en HTML : une ligne par <br> ou par <p>,
     * les lignes « - » consécutives forment une liste.
     */
    private static function markdown(string $html): string
    {
        if (!preg_match('/\*\*|(<p>|<br>)\s*(- |#{1,6} )|\]\(https?:/', $html)) {
            return $html;
        }

        // Gras et liens, y compris dans les listes HTML déjà présentes.
        $html = preg_replace('/\*\*([^<]+?)\*\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace('/\[([^\]<]+)\]\((https?:\/\/[^)\s<]+)\)/u', '<a href="$2">$1</a>', $html);
        $inline = static fn (string $text): string => trim($text);

        // Découpe en jetons : chaque ligne d'un <p> devient un jeton typé, les autres blocs restent tels quels.
        $tokens = [];
        foreach (preg_split('#(<p>.*?</p>)#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $block) {
            if (!str_starts_with($block, '<p>')) {
                $tokens[] = ['raw', $block];
                continue;
            }
            foreach (preg_split('#\s*<br>\s*#', substr($block, 3, -4)) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^#{1,6}\s+(?:\d+\.\s*)?(.+)$/u', $line, $m)) {
                    $tokens[] = ['h3', rtrim($inline($m[1]), ' :')];
                } elseif (preg_match('/^[-*•]\s+(.+)$/u', $line, $m)) {
                    $tokens[] = ['li', $inline($m[1])];
                } else {
                    $tokens[] = ['p', $inline($line)];
                }
            }
        }

        $out = '';
        $inList = false;
        foreach ($tokens as [$type, $content]) {
            if ($type === 'li' && !$inList) {
                $out .= '<ul>';
                $inList = true;
            } elseif ($type !== 'li' && $inList) {
                $out .= '</ul>';
                $inList = false;
            }
            $out .= match ($type) {
                'raw' => $content,
                'h3' => "<h3>{$content}</h3>",
                'li' => "<li>{$content}</li>",
                'p' => "<p>{$content}</p>",
            };
        }

        return $out . ($inList ? '</ul>' : '');
    }

    private static function cleanNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            self::cleanNode($child);

            if (in_array($tag, self::REMOVED, true)) {
                $node->removeChild($child);
                continue;
            }
            if (isset(self::RENAMED[$tag])) {
                $renamed = $child->ownerDocument->createElement(self::RENAMED[$tag]);
                while ($child->firstChild) {
                    $renamed->appendChild($child->firstChild);
                }
                $node->replaceChild($renamed, $child);
                $child = $renamed;
                $tag = self::RENAMED[$tag];
            }
            if (!in_array($tag, self::ALLOWED, true)) {
                // div, span, font… : garder le contenu, retirer la balise. Un div devient une fin de ligne.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                if ($tag === 'div') {
                    $node->insertBefore($child->ownerDocument->createElement('br'), $child);
                }
                $node->removeChild($child);
                continue;
            }

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttribute($attribute->name);
            }
            if ($tag === 'a') {
                if (preg_match('#^(https?://|mailto:|tel:)#i', $href)) {
                    $child->setAttribute('href', $href);
                } else {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            }
        }
    }

    /** Fichier PHP lisible renvoyant $data. */
    public static function toPhpFile(array $data, string $comment): string
    {
        return "<?php\n\n// {$comment}\n\ndeclare(strict_types=1);\n\nreturn " . self::export($data, 0) . ";\n";
    }

    private static function export(mixed $value, int $depth): string
    {
        if (!is_array($value)) {
            return var_export($value, true);
        }
        if ($value === []) {
            return '[]';
        }
        $indent = str_repeat('    ', $depth + 1);
        $list = array_is_list($value);
        $lines = [];
        foreach ($value as $key => $item) {
            $lines[] = $indent . ($list ? '' : var_export($key, true) . ' => ') . self::export($item, $depth + 1) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . ']';
    }
}
