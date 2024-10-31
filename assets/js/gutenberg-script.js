let oxyPrefetchPreviousData = {};
const saveLinks = async (postId, data) => {
  try {
    await wp.apiFetch({
      path: '/oxy-prefetch/v1/save-links/',
      method: 'POST',
      data: {
        post_id: postId,
        data: data,
      },
    })
  } catch (error) {
    alert(error.data.message);
  }
}

wp.data.subscribe(() => {
  const editor = wp.data.select('core/editor');
  const isSavingPost = editor.isSavingPost();
  const postStatus = editor.getEditedPostAttribute('status');
  if (isSavingPost && ['publish', 'private', 'protected'].includes(postStatus)) {
    let data = jQuery('#oxy_prefetch_metabox').find(':input').serializeArray();
    data = data.reduce((obj, input) => {
      const { name, value } = input;
      const keys = name.split('[');
      let current = obj;
      for (let i = 0; i < keys.length - 1; i++) {
        const key = keys[i].replace(/]$/, '');
        if (!(key in current)) {
          current[key] = {};
        }
        current = current[key];
      }
      current[keys[keys.length - 1].replace(/]$/, '')] = value;
      return obj;
    }, {});

    if (JSON.stringify(oxyPrefetchPreviousData) !== JSON.stringify(data)) {
      oxyPrefetchPreviousData = data;
      saveLinks(editor.getCurrentPostId(), data);
    }
  }
});