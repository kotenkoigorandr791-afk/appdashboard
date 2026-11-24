package orbit.nex.gitea.api.clients;

import java.util.List;
import orbit.nex.gitea.api.models.contents.RepoGetContentsList;
import orbit.nex.gitea.api.models.license.License;
import orbit.nex.gitea.api.models.settings.RepositoryGlobal;
import orbit.nex.gitea.api.models.topics.Topics;
import orbit.nex.gitea.api.models.users.UserSearchResponse;
import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.DELETE;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.PUT;
import retrofit2.http.Path;
import retrofit2.http.Query;

public interface ApiInterface {

	@GET("repos/{owner}/{repo}/contents")
	Call<List<RepoGetContentsList>> getRepoContents(
			@Path("owner") String owner, @Path("repo") String repo, @Query("ref") String ref);

	@GET("repos/{owner}/{repo}/contents/{path}")
	Call<List<RepoGetContentsList>> getRepoContents(
			@Path("owner") String owner,
			@Path("repo") String repo,
			@Path("path") String path,
			@Query("ref") String ref);

	@GET("repos/{owner}/{repo}/topics") // get list of topics for a repo
	Call<Topics> getRepoTopics(
			@Path("owner") String owner,
			@Path("repo") String repo,
			@Query("page") int page,
			@Query("limit") int limit);

	@PUT("repos/{owner}/{repo}/topics/{topic}") // add a new topic for a repo
	Call<Void> addRepoTopic(
			@Path("owner") String owner, @Path("repo") String repo, @Path("topic") String topic);

	@DELETE("repos/{owner}/{repo}/topics/{topic}") // delete a repo topic
	Call<Void> deleteRepoTopic(
			@Path("owner") String owner, @Path("repo") String repo, @Path("topic") String topic);

	@GET("settings/repository") // get repository global settings
	Call<RepositoryGlobal> getRepositoryGlobalSettings();

	@GET("gitignore/templates") // get all gitignore templates
	Call<List<String>> getGitignoreTemplates();

	@GET("licenses") // get all licenses
	Call<List<License>> getLicenses();

	@GET("users/search") // get all users
	Call<UserSearchResponse> customUserSearch(
			@Query("q") String query,
			@Query("uid") Long uid,
			@Query("sort") String sort,
			@Query("page") Integer page,
			@Query("limit") Integer limit);
}
