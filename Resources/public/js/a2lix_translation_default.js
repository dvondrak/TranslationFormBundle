$(function () {
    $('.a2lix_translationsLocales').on('click', 'a', function (event) {
        event.preventDefault();
        var target = $(this).data('target');
        var $tab = $('.a2lix_translationsLocales li:has(a[data-target="' + target + '"]), ' + target);
        $tab.addClass('active');
        $tab.siblings().removeClass('active');
    });

    $('.a2lix_translationsLocalesSelector').each(function () {
        var $this = $(this);
        var $tabs = $('.a2lix_translationsLocales');
        var rootId = $this.data('rootId');
        $this.find('input')
            .change(function (event) {
                var target = '#' + rootId + " [class*='_a2lix_translationsFields-" + this.value + "']";
                var checked = this.checked;
                var $tab = $tabs.find('li:has(a[data-target="' + target + '"])');

                $tab.each(function () {
                    if ($(this).is('.active') && !checked) {
                        $tab.siblings().first().find('a').trigger('click');
                    }
                });

                $tab.toggle(this.checked);
            })
            .trigger('change');
    });
});
