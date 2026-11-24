package orbit.nex.gitea.helpers;

import android.content.Context;
import android.content.Intent;
import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import orbit.nex.gitea.R;
import orbit.nex.gitea.activities.LoginActivity;

public class TokenAuthorizationDialog {

	public static void authorizationTokenRevokedDialog(final Context context) {

		MaterialAlertDialogBuilder materialAlertDialogBuilder =
				new MaterialAlertDialogBuilder(
						context, R.style.ThemeOverlay_Material3_Dialog_Alert);

		materialAlertDialogBuilder
				.setTitle(R.string.alertDialogTokenRevokedTitle)
				.setMessage(R.string.alertDialogTokenRevokedMessage)
				.setCancelable(true)
				.setNeutralButton(R.string.cancelButton, null)
				.setPositiveButton(
						R.string.update_account,
						(dialog, which) -> {
							Intent intent = new Intent(context, LoginActivity.class);
							intent.putExtra("mode", "update_account");
							context.startActivity(intent);
						})
				.show();
	}
}
