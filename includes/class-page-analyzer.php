<?php
if (!defined('ABSPATH')) {
    exit;
}

class PSMAnalyzer
{
    //ключи - это комменты, которые потом заменяются на значения через str_replace
    protected $placeholders = array(
        '-<!--total_mb_placeholder-->'  => 'Общий вес страницы',
        '-<!--html_mb_placeholder-->'  => 'Вес без изображений',
        '-<!--code_mb_placeholder-->' => 'Вес скриптов и стилей',
        '-<!--text_mb_placeholder-->' => 'Вес текста',
        '-<!--images_mb_placeholder-->' => 'Вес изображений',
        '-<!--images_placeholder-->' => 'Тяжёлые изображения',
        '-<!--images_list_placeholder-->' => '',
    );

    protected $stats = array(
        'total_bytes'  => 0,
        'html_bytes' => 0,
        'code_bytes'   => 0,
        'text_bytes'   => 0,
        'image_bytes'  => 0,
        'images' => array(),
    );

    public function __construct()
    {
        add_action('template_redirect', [$this, 'start']);
        add_action('admin_bar_menu', [$this, 'display_stats'], 999);
        add_action('wp_footer', [$this, 'render_images_modal']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function start()
    {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        ob_start(array($this, 'handle_buffer'));
    }

    // считает и меняет плейсхолдеры на итоговые значения
    public function handle_buffer($html)
    {
        if (is_string($html) && '' !== $html) {
            $this->analyze($html);
        }

        $formatted = $this->get_stats_formatted();
        $search = array_keys($this->placeholders);
        $replace = array_values($formatted);

        return str_replace($search, $replace, $html);
    }

    // регистрация скрипта и стилей для модального окна картинок
    public function enqueue_assets()
    {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
        wp_enqueue_style(
            'psa-modal',
            plugins_url('assets/css/style.css', PSM_PLUGIN_FILE),
            array(),
            PSM_VERSION
        );

        wp_enqueue_script(
            'psa-modal',
            plugins_url('assets/js/index.js', PSM_PLUGIN_FILE),
            array(),
            PSM_VERSION,
            true
        );
    }

    protected function analyze($html)
    {
        $this->stats['html_bytes'] = strlen($html);

        // скрипты и стили, которая использует страница
        $inline_code_bytes = 0;

        $html_without_scripts = preg_replace_callback(
            '#<(script|style)\b[^>]*>(.*?)</\1>#is',
            function ($matches) use (&$inline_code_bytes) {
                $inline_code_bytes += strlen($matches[0]);
                return '';
            },
            $html
        );

        // вес картинок
        $this->analyze_images($html);

        $tags_bytes = 0;
        $text_bytes = 0;

        // вес тегов хмтл
        preg_replace_callback(
            '#<[^>]+>#s',
            function ($matches) use (&$tags_bytes) {
                $tags_bytes += strlen($matches[0]);
                return '';
            },
            $html_without_scripts
        );

        // текст
        $plain_text = preg_replace('#<[^>]+>#s', '', $html_without_scripts);
        $plain_text = trim(preg_replace('/\s+/', ' ', $plain_text));
        $text_bytes = strlen($plain_text);

        $this->stats['code_bytes'] = $inline_code_bytes + $tags_bytes;
        $this->stats['text_bytes'] = $text_bytes;

        // общий вес
        $this->stats['total_bytes'] = $this->stats['html_bytes'] + $this->stats['code_bytes'] + $text_bytes + $this->stats['image_bytes'];
    }

    protected function analyze_images($html)
    {
        $urls = array();

        // <img src="...">
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            $urls = array_merge($urls, $matches[1]);
        }

        // srcset="url1 1x, url2 2x"
        if (preg_match_all('/srcset=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $srcset) {
                foreach (explode(',', $srcset) as $part) {
                    $part = trim(explode(' ', trim($part))[0]);
                    if ($part) {
                        $urls[] = $part;
                    }
                }
            }
        }

        // background-image: url(...)
        if (preg_match_all('/background(?:-image)?\s*:\s*url\(([^)]+)\)/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $urls[] = trim($url, "'\" ");
            }
        }

        $urls = array_unique($urls);

        foreach ($urls as $url) {
            $path = $this->url_to_local_path($url);

            if ($path && file_exists($path)) {
                $size = filesize($path);
                $is_critical = false;

                // тяжёлые картинки помечаю
                if ($size > 150000) $is_critical = true;

                $this->stats['image_bytes'] += $size;
                $this->stats['images'][] = array(
                    'url'   => $url,
                    'critical' => $is_critical,
                    'name'  => basename(parse_url($url, PHP_URL_PATH)),
                    'bytes' => $size,
                    'size'  => round($size / (1024 * 1024), 2) . ' MB',
                );
            }
        }
    }

    //какая-то жёсткая функция от клода для путей картинок и зашиты от Path traversal
    protected function url_to_local_path($url)
    {
        if (strpos($url, 'http') !== 0) {
            $path = ABSPATH . ltrim($url, '/');
        } else {
            $site_url = site_url();

            $url_no_scheme = preg_replace('#^https?://#i', '', $url);
            $site_no_scheme = preg_replace('#^https?://#i', '', $site_url);

            if (strpos($url_no_scheme, $site_no_scheme) !== 0) {
                return false;
            }

            $relative = substr($url_no_scheme, strlen($site_no_scheme));
            $path = ABSPATH . ltrim($relative, '/');
        }

        $real = realpath($path);
        $abspath_real = realpath(ABSPATH);

        if ($real === false || $abspath_real === false || strpos($real, $abspath_real) !== 0) {
            return false;
        }

        return $real;
    }

    // собираю всё инфу в нормальном виде
    public function get_stats_formatted()
    {
        usort($this->stats['images'], function ($a, $b) {
            return $b['bytes'] <=> $a['bytes'];
        });

        $table_rows_string = '';

        foreach ($this->stats['images'] as $img) {
            $row_style = $img['critical'] ? 'background-color: #ffe6e6;' : 'background-color: #ffffff;';

            $table_rows_string .= '<tr style="' . $row_style . '">';
            $table_rows_string .= '<td style="width: 50px; text-align: center;">';
            $table_rows_string .= '<img src="' . esc_url($img['url']) . '" class="wp-hi-img">';
            $table_rows_string .= '</td>';
            $table_rows_string .= '<td><strong>' . esc_html($img['name']) . '</strong></td>';
            $table_rows_string .= '<td>' . esc_html($img['size']) . '</td>';
            $table_rows_string .= '<td><a href="' . esc_url($img['url']) . '" target="_blank">Открыть</a></td>';
            $table_rows_string .= '</tr>';
        }

        $mb = function ($bytes) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        };

        return array(
            'total_mb' => $mb($this->stats['total_bytes']),
            'html_mb' => $mb($this->stats['html_bytes']),
            'code_mb'  => $mb($this->stats['code_bytes']),
            'text_mb'  => $mb($this->stats['text_bytes']),
            'images_mb' => $mb($this->stats['image_bytes']) . ' (' . count($this->stats['images']) . ' шт.)',
            'images' =>  count(array_filter($this->stats['images'], function ($img) {
                return !empty($img['critical']);
            })),
            'images_list' =>  $table_rows_string,
        );
    }

    // менюшка 
    public function display_stats($admin_bar)
    {
        $firstKey = array_key_first($this->placeholders);
        // родительская кнопка
        $admin_bar->add_node(array(
            'id' => 'display_page_info_parent',
            'title' => $this->placeholders[$firstKey] . ': ' . $firstKey,
        ));

        // убираю первый родительский и последний, тк это плейсхолдер для таблицы с картинками
        $child_nodes = array_slice($this->placeholders, 1, null, true);
        array_pop($child_nodes);

        $index = 1;
        foreach ($child_nodes as $placeholder => $label) {
            $admin_bar->add_node([
                'id'     => 'display_page_info_child_' . $index,
                'parent' => 'display_page_info_parent',
                'title'  => $label . ': ' . $placeholder,
            ]);
            $index++;
        }

        //кнопка для открытия модального окна
        $admin_bar->add_node([
            'id'    => 'display_page_info_child_' . $index,
            'parent' => 'display_page_info_parent',
            'title'  => 'Анализ изображений',
            'href'  => '#',
            'meta'   => [
                'title' => 'Нажать чтобы посмотреть',
            ],
        ]);
    }

    // окно где показываются все картинки со страницы
    function render_images_modal()
    {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
            return;
        }
?>
        <div id="wp-heavy-images-modal" class="wp-hi-modal" style="display: none;">
            <div class="wp-hi-modal-content">
                <div class="wp-hi-modal-header">
                    <h3>Анализ изображений страницы</h3>
                    <span class="wp-hi-modal-close">&times;</span>
                </div>
                <div class="wp-hi-modal-body">
                    <table class="wp-hi-table">
                        <thead>
                            <tr>
                                <th>Превью</th>
                                <th>Файл</th>
                                <th>Размер</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            -<!--images_list_placeholder-->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php
    }
}
