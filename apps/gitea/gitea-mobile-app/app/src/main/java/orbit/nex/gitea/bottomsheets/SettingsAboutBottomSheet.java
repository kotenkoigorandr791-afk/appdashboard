package orbit.nex.gitea.bottomsheets;

import android.app.Dialog;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import orbit.nex.gitea.R;
import orbit.nex.gitea.activities.BaseActivity;
import orbit.nex.gitea.databinding.BottomsheetSettingsAboutBinding;
import orbit.nex.gitea.helpers.AppUtil;

public class SettingsAboutBottomSheet extends BottomSheetDialogFragment {

	private BottomsheetSettingsAboutBinding binding;

	@Nullable @Override
	public View onCreateView(
			@NonNull LayoutInflater inflater,
			@Nullable ViewGroup container,
			@Nullable Bundle savedInstanceState) {
		binding = BottomsheetSettingsAboutBinding.inflate(inflater, container, false);

		// Set app version and build
		binding.appVersionBuild.setText(
				getString(
						R.string.appVersionBuild,
						AppUtil.getAppVersion(requireContext()),
						AppUtil.getAppBuildNo(requireContext())));

		// Set server version
		binding.userServerVersion.setText(
				((BaseActivity) requireActivity()).getAccount().getServerVersion().toString());

		// Only the NexOrbit portal is linked from this screen.
		binding.appWebsite.setOnClickListener(
				v -> {
					AppUtil.openUrlInBrowser(requireContext(), getString(R.string.appWebsiteLink));
					dismiss();
				});

		return binding.getRoot();
	}

	@Override
	public void onStart() {
		super.onStart();
		Dialog dialog = getDialog();
		if (dialog instanceof BottomSheetDialog) {
			AppUtil.applySheetStyle((BottomSheetDialog) dialog, true);
		}
	}

	@Override
	public void onDestroyView() {
		super.onDestroyView();
		binding = null;
	}
}
