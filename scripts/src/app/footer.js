export default function initFooter(action = 'init') {
  let footer = document.getElementById('colophon');
  if (!footer) return;
  let ticking = false;
  let delayedCheck = 0;

  // 隐藏 footer
  function hideFooter() {
    footer && footer.classList.remove('show');
  }

  function adjustWrapperPadding() {
    const wrapper = document.querySelector('.site.wrapper');
    if (!wrapper) return;
    const footerHeight = footer.offsetHeight;
    const paddingValue = footerHeight * 1.3;
    wrapper.style.paddingBottom = `${paddingValue}px`;
  }

  function checkFooterVisibility() {
    const scrollPosition = window.scrollY || document.documentElement.scrollTop;
    const windowHeight   = window.innerHeight;
    const documentHeight = Math.max(
      document.documentElement.scrollHeight,
      document.body.scrollHeight
    );
    const showThreshold  = documentHeight - 100;

    footer.classList.toggle('show', scrollPosition + windowHeight >= showThreshold);
  }

  function scheduleVisibilityCheck() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      checkFooterVisibility();
      ticking = false;
    });
  }

  function onScroll() {
    scheduleVisibilityCheck();
  }

  function onResize() {
    adjustWrapperPadding();
    scheduleVisibilityCheck();
  }

  function initialize() {
    footer = document.getElementById('colophon');
    if (!footer) return;
    // 初始化隐藏
    hideFooter();
    adjustWrapperPadding();

    // 解绑旧的监听，避免重复
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onResize);

    // 首次检查（延迟100ms，保证页面渲染完成后也能正确显示）
    checkFooterVisibility();
    window.clearTimeout(delayedCheck);
    delayedCheck = window.setTimeout(scheduleVisibilityCheck, 100);

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onResize, { passive: true });
  }

  // 监听 PJAX 完成后再次初始化
  document.addEventListener('pjax:complete', initialize);

  // ———— 根据 action 分发 ————
  switch (action) {
    case 'init':
      initialize();
      break;
    case 'hide':
      hideFooter();
      break;
    case 'check':
      checkFooterVisibility();
      break;
    default:
      initialize();
  }
}
