(function ($) {
  $(function () {
    function initSelect($el) {
      if (!$el || !$el.length) return;
      $el.each(function () {
        var $this = $(this);
        if ($this.data("select2")) return;
        $this.select2({
          ajax: {
            url: wbogo_admin.ajax_url,
            dataType: "json",
            delay: 250,
            data: function (params) {
              return {
                q: params.term,
                action: "wbogo_product_search",
              };
            },
            processResults: function (data) {
              return { results: data };
            },
          },
          placeholder: wbogo_admin.i18n_search_placeholder,
          minimumInputLength: 1,
          width: "300px",
        });
      });
    }

    function bindButtons(context) {
      $(context).on("click", ".wbogo-add-rule", function (e) {
        e.preventDefault();
        var idx = $("#wbogo_rules_container .wbogo-rule").length;
        var tpl = $("#wbogo_rule_template")
          .html()
          .replace(/__RULE_INDEX__/g, idx);
        $("#wbogo_rules_container").append(tpl);
      });

      $(context).on("click", ".wbogo-remove-rule", function (e) {
        e.preventDefault();
        $(this).closest(".wbogo-rule").remove();
      });

      $(context).on("click", ".wbogo-add-free", function (e) {
        e.preventDefault();
        var $rule = $(this).closest(".wbogo-rule");
        var ruleIndex = $rule.data("rule-index");
        if (typeof ruleIndex === "undefined") {
          ruleIndex = $("#wbogo_rules_container .wbogo-rule").index($rule);
          $rule.attr("data-rule-index", ruleIndex);
        }
        var $list = $rule.find(".wbogo-free-list");
        var idx = $list.data("index") || $list.find(".wbogo-free-row").length;
        var tpl = $("#wbogo_free_template")
          .html()
          .replace(/__RULE_INDEX__/g, ruleIndex)
          .replace(/__INDEX__/g, idx);
        $list.append(tpl);
        $list.data("index", idx + 1);
        initSelect($list.find("select.wbogo-product-select"));
      });

      $(context).on("click", ".remove-free", function (e) {
        e.preventDefault();
        $(this).closest(".wbogo-free-row").remove();
      });
    }

    initSelect($("select.wbogo-product-select"));
    bindButtons(document);
  });
})(jQuery);
