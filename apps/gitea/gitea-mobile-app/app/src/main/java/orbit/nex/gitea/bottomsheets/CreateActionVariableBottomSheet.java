package orbit.nex.gitea.bottomsheets;

import android.app.Dialog;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewGroup;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.lifecycle.ViewModelProvider;
import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import orbit.nex.gitea.R;
import orbit.nex.gitea.activities.BaseActivity;
import orbit.nex.gitea.databinding.BottomsheetCreateRepoActionVariableBinding;
import orbit.nex.gitea.helpers.AppUIStateManager;
import orbit.nex.gitea.helpers.AppUtil;
import orbit.nex.gitea.helpers.Toasty;
import orbit.nex.gitea.viewmodels.RepositoryActionsViewModel;

public class CreateActionVariableBottomSheet extends BottomSheetDialogFragment {

	private BottomsheetCreateRepoActionVariableBinding binding;
	private RepositoryActionsViewModel giteaViewModel;
	private String owner;
	private String repo;

	public static CreateActionVariableBottomSheet newInstance(String owner, String repo) {
		CreateActionVariableBottomSheet fragment = new CreateActionVariableBottomSheet();
		Bundle args = new Bundle();
		args.putString("owner", owner);
		args.putString("repo", repo);
		fragment.setArguments(args);
		return fragment;
	}

	@Override
	public void onCreate(@Nullable Bundle savedInstanceState) {
		super.onCreate(savedInstanceState);
		if (getArguments() != null) {
			owner = getArguments().getString("owner");
			repo = getArguments().getString("repo");
		}
	}

	@Nullable @Override
	public View onCreateView(
			@NonNull LayoutInflater inflater,
			@Nullable ViewGroup container,
			@Nullable Bundle savedInstanceState) {
		binding = BottomsheetCreateRepoActionVariableBinding.inflate(inflater, container, false);
		return binding.getRoot();
	}

	@Override
	public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
		super.onViewCreated(view, savedInstanceState);

		giteaViewModel =
				new ViewModelProvider(requireActivity()).get(RepositoryActionsViewModel.class);
		giteaViewModel.clearVariableCreated();
		giteaViewModel.clearCreateVariableError();

		binding.variableValue.setOnTouchListener(
				(v, event) -> {
					if (event.getAction() == MotionEvent.ACTION_DOWN) {
						v.getParent().requestDisallowInterceptTouchEvent(true);
					} else if (event.getAction() == MotionEvent.ACTION_UP
							|| event.getAction() == MotionEvent.ACTION_CANCEL) {
						v.getParent().requestDisallowInterceptTouchEvent(false);
						v.performClick();
					}
					return false;
				});

		binding.variableDescription.setOnTouchListener(
				(v, event) -> {
					if (event.getAction() == MotionEvent.ACTION_DOWN) {
						v.getParent().requestDisallowInterceptTouchEvent(true);
					} else if (event.getAction() == MotionEvent.ACTION_UP
							|| event.getAction() == MotionEvent.ACTION_CANCEL) {
						v.getParent().requestDisallowInterceptTouchEvent(false);
						v.performClick();
					}
					return false;
				});

		setupListeners();
		observeViewModel();
	}

	private void setupListeners() {
		binding.btnClose.setOnClickListener(v -> dismiss());
		binding.createButton.setOnClickListener(v -> createVariable());
	}

	private void createVariable() {
		String name =
				binding.variableName.getText() != null
						? binding.variableName.getText().toString().trim()
						: "";
		String value =
				binding.variableValue.getText() != null
						? binding.variableValue.getText().toString().trim()
						: "";
		String description =
				binding.variableDescription.getText() != null
						? binding.variableDescription.getText().toString().trim()
						: "";

		if (name.isEmpty()) {
			Toasty.show(requireContext(), R.string.variable_name_error);
			return;
		}
		if (!name.matches("^[a-zA-Z0-9_]+$")) {
			Toasty.show(requireContext(), R.string.variable_name_invalid);
			return;
		}
		if (value.isEmpty()) {
			Toasty.show(requireContext(), R.string.variable_value_error);
			return;
		}

		giteaViewModel.createVariable(requireContext(), owner, repo, name, value, description);
	}

	private void observeViewModel() {
			giteaViewModel
					.getIsCreatingVariable()
					.observe(
							getViewLifecycleOwner(),
							isCreating -> {
								binding.createButton.setEnabled(!isCreating);
								binding.createButton.setText(
										isCreating ? "" : getString(R.string.newCreateButtonCopy));
							});

			giteaViewModel
					.getVariableCreated()
					.observe(
							getViewLifecycleOwner(),
							created -> {
								if (created != null && created) {
									Toasty.show(requireContext(), R.string.variable_create_success);
									giteaViewModel.resetVariablesPagination();
									dismiss();
								}
							});

			giteaViewModel
					.getCreateVariableError()
					.observe(
							getViewLifecycleOwner(),
							error -> {
								if (error != null && !error.isEmpty()) {
									Toasty.show(requireContext(), error);
									giteaViewModel.clearCreateVariableError();
								}
							});
	}

	@Override
	public void onStart() {
		super.onStart();
		Dialog dialog = getDialog();
		if (dialog instanceof BottomSheetDialog) {
			AppUtil.applySheetStyle((BottomSheetDialog) dialog, false);
		}
	}

	@Override
	public void onDestroyView() {
		super.onDestroyView();
		binding = null;
	}
}
