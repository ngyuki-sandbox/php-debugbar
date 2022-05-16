(function($) {

    const className = 'phpdebugbar-widgets-ritz-database';

    const template = `
        <div class="${className}-stmt">
            <div class="${className}-headline">
                <span class="${className}-sql">
                    <code data-selection></code>
                </span>
                <a href="#" data-target=".${className}-params" title="Params">Params</a>
                <a href="#" data-target=".${className}-backtrace" title="Backtrace">Backtrace</a>
                <a href="#" data-target=".${className}-explain" title="Explain">Explain</a>
                <a href="#" data-target=".${className}-error" class="${className}-error-link" title="Error">Error</a>
                <span class="${className}-stat">
                    <span class="${className}-rows phpdebugbar-fa-table" title="Row count">123</span>
                    <span class="${className}-memory phpdebugbar-fa-cogs" title="Memory Usage">123KB</span>
                    <span class="${className}-duration phpdebugbar-fa-clock-o" title="Duration">61μs</span>
                </span>
            </div>
            <table class="${className}-collapse ${className}-params">
                <tr>
                    <th>1</th>
                    <td>xxx</td>
                </tr>
            </table>
            <ul class="${className}-collapse ${className}-backtrace">
                <li>
                    <code data-selection>Class::func</code>
                    <i>in</i>
                    <span data-selection>/path/to/file.php:123</span>
                </li>
            </ul>
            <table class="${className}-collapse ${className}-explain"></table>
            <div class="${className}-collapse ${className}-error"></div>
        </div>
    `;

    PhpDebugBar.Widgets.Ritz = PhpDebugBar.Widgets.Ritz || {};
    PhpDebugBar.Widgets.Ritz.Database = PhpDebugBar.Widget.extend({

        className: className,

        render: function() {
            this.$el.empty();

            this.$el.on('click', `[data-target]`, (ev) => {
                const $elem = $(ev.currentTarget);
                const $stmt = $elem.closest(`.${className}-stmt`);
                const $area = $stmt.find($elem.attr('data-target'));
                const visible = $area.is(':visible');
                $stmt.find(`.${className}-collapse`).hide('fast');
                if (!visible) {
                    $area.show('fast');
                }
            });

            this.$el.on('click', `[data-selection]`, (ev) => {
                if (window.getSelection().isCollapsed) {
                    const range = document.createRange();
                    range.selectNodeContents(ev.currentTarget);
                    window.getSelection().removeAllRanges();
                    window.getSelection().addRange(range);
                }
            });

            this.bindAttr('data', function(data) {
                this.$el.empty();

                for (const stmt of data.statements) {
                    const $stmt = $(template);

                    $stmt.find(`.${className}-sql > code`).html(PhpDebugBar.Widgets.highlight(stmt.sql, 'sql'));

                    if (!stmt.row_count) {
                        $stmt.find(`.${className}-rows`).hide();
                    } else {
                        $stmt.find(`.${className}-rows`).text(stmt.row_count).show();
                    }

                    if (!stmt.memory_str) {
                        $stmt.find(`.${className}-memory`).hide();
                    } else {
                        $stmt.find(`.${className}-memory`).text(stmt.memory_str).show();
                    }

                    if (!stmt.duration_str) {
                        $stmt.find(`.${className}-duration`).hide();
                    } else {
                        $stmt.find(`.${className}-duration`).text(stmt.duration_str).show();
                    }

                    const $params = $stmt.find(`.${className}-params`).empty();
                    if (!stmt.params || Object.keys(stmt.params).length === 0) {
                        $stmt.find(`[data-target=".${className}-params"]`).hide();
                    } else {
                        for (const [name, param] of Object.entries(stmt.params)) {
                            $params.append(
                                $('<tr>').append(
                                    $('<th>').text(name),
                                    $('<td>').text(JSON.stringify(param, null, 2)),
                                ),
                            )
                        }
                    }

                    const $backtrace = $stmt.find(`.${className}-backtrace`).empty();
                    if (!stmt.backtrace || stmt.backtrace.length === 0) {
                        $stmt.find(`[data-target=".${className}-backtrace"]`).hide();
                    } else {
                        for (const f of stmt.backtrace) {
                            if (f.omit) {
                                $backtrace.append(
                                    $('<li>').append(
                                        $('<i>').text(`... omit ${f.omit} lines`)
                                    )
                                )
                            } else {
                                $backtrace.append(
                                    $('<li>').append(
                                        $('<code data-selection>').text(`${f.name}`),
                                        $('<i>').text(` in `),
                                        $('<span data-selection>').text(`${f.file}:${f.line}`)
                                    )
                                )
                            }
                        }
                    }

                    const $explain = $stmt.find(`.${className}-explain`).empty();
                    if (!stmt.explain || !stmt.explain.rows || stmt.explain.rows.length === 0) {
                        $stmt.find(`[data-target=".${className}-explain"]`).hide();
                    } else {
                        const $tr = $('<tr>').appendTo($explain);
                        for (const column of stmt.explain.columns) {
                            $tr.append($('<th>').text(column));
                        }
                        for (const row of stmt.explain.rows) {
                            const $tr = $('<tr>').appendTo($explain);
                            for (const val of row) {
                                $tr.append($('<th>').text(val));
                            }
                        }
                    }

                    const $error = $stmt.find(`.${className}-error`).empty();
                    if (!stmt.error) {
                        $stmt.find(`[data-target=".${className}-error"]`).hide();
                    } else {
                        $error.text(stmt.error);
                    }

                    this.$el.append($stmt);
                }
            });
        },
    });

})(PhpDebugBar.$);
