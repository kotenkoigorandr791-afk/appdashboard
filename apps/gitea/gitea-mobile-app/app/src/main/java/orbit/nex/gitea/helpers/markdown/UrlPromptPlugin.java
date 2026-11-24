package orbit.nex.gitea.helpers.markdown;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import io.noties.markwon.AbstractMarkwonPlugin;
import io.noties.markwon.LinkResolver;
import io.noties.markwon.MarkwonConfiguration;
import orbit.nex.gitea.R;
import orbit.nex.gitea.helpers.AppUtil;

public class UrlPromptPlugin extends AbstractMarkwonPlugin {

	public static UrlPromptPlugin create() {
		return new UrlPromptPlugin();
	}

	private UrlPromptPlugin() {}

	@Override
	public void configureConfiguration(@NonNull MarkwonConfiguration.Builder builder) {

		LinkResolver promptResolver =
				(view, link) -> {
					if (link.startsWith("gitnex://")) {
						return;
					}

					new AlertDialog.Builder(view.getContext())
							.setTitle(R.string.isOpen)
							.setMessage(link)
							.setPositiveButton(
									R.string.isOpen,
									(dialog, which) ->
											AppUtil.openUrlInBrowser(view.getContext(), link))
							.setNegativeButton(R.string.cancelButton, null)
							.show();
				};

		builder.linkResolver(promptResolver);
	}
}
