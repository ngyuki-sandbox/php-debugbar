(function($) {

    const className = 'phpdebugbar-widgets-setting';

    PhpDebugBar.Widgets.Setting = PhpDebugBar.Widget.extend({

        className: className,

        render: function() {
            this.$el.html(`
                <textarea></textarea>
                <div>
                    <button class="${className}-default" type="button">Restore default</button>
                    <button class="${className}-discard" type="button">Discard changes</button>
                    <button class="${className}-save"    type="button">Save and reload</button>
                </div>
            `);

            const $textarea = this.$el.find('textarea');

            this.$el.find(`.${className}-default`).on('click', () => {
                $textarea.val($textarea.attr('data-default'));
            });

            this.$el.find(`.${className}-discard`).on('click', () => {
                $textarea.val($textarea.attr('data-saved'));
            });

            this.$el.find(`.${className}-save`).on('click', () => {
                saveCookie($textarea.attr('data-cookie'), $textarea.val());
                location.reload();
            });

            function loadCookie(cookieName, defaultValue) {
                const cookie = document.cookie.split(';').find(row => row.trim().startsWith(`${cookieName}=`));
                if (!cookie) {
                    return defaultValue;
                }
                return decodeURIComponent(cookie.split('=')[1]);
            }

            function saveCookie(cookieName, value) {
                return document.cookie = `${cookieName}=` + encodeURIComponent(value);
            }

            this.bindAttr('data', function(data) {
                const savedValue = loadCookie(data.cookie, data.default);
                $textarea.attr('data-cookie', data.cookie);
                $textarea.attr('data-default', data.default);
                $textarea.attr('data-saved', savedValue);
                $textarea.val(savedValue);
            });
        },
    });

})(PhpDebugBar.$);
