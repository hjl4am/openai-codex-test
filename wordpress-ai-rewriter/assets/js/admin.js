(function ($) {
  $(function () {
    const $btn = $('#wpair-rewrite-btn');
    const $status = $('#wpair-rewrite-status');

    if (!$btn.length || typeof wpairData === 'undefined') {
      return;
    }

    $btn.on('click', function () {
      $status.text('正在调用模型改写，请稍候...');
      $btn.prop('disabled', true);

      $.post(wpairData.ajaxUrl, {
        action: 'wpair_rewrite_post',
        nonce: wpairData.nonce,
        postId: wpairData.postId,
      })
        .done(function (res) {
          if (res.success) {
            $status.text(res.data.message + ' 请刷新页面查看最新内容。');
          } else {
            $status.text('改写失败：' + (res.data?.message || '未知错误'));
          }
        })
        .fail(function () {
          $status.text('请求失败，请检查网络或服务配置。');
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });
  });
})(jQuery);
