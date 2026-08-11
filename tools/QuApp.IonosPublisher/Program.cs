using Renci.SshNet;
using Renci.SshNet.Sftp;

if (args.Length > 0 && string.Equals(args[0], "list", StringComparison.OrdinalIgnoreCase))
{
    if (args.Length < 6)
    {
        Console.Error.WriteLine("Usage: QuApp.IonosPublisher list <host> <port> <username> <password> <remotePath>");
        return 2;
    }

    using var listClient = new SftpClient(args[1], int.Parse(args[2]), args[3], args[4]);
    listClient.ConnectionInfo.Timeout = TimeSpan.FromSeconds(30);
    listClient.Connect();
    var listPath = NormalizeRemote(args[5]);
    foreach (var item in listClient.ListDirectory(listPath).OrderBy(item => item.Name))
    {
        if (item.Name is "." or "..")
        {
            continue;
        }
        Console.WriteLine($"{(item.IsDirectory ? "d" : "-")} {item.Length,12} {item.LastWriteTime:yyyy-MM-dd HH:mm:ss} {item.FullName}");
    }
    listClient.Disconnect();
    return 0;
}

if (args.Length < 6)
{
    Console.Error.WriteLine("Usage: QuApp.IonosPublisher <host> <port> <username> <password> <localRoot> <remoteRoot> [--include-generated-reports]");
    return 2;
}

var host = args[0];
var port = int.Parse(args[1]);
var username = args[2];
var password = args[3];
var localRoot = Path.GetFullPath(args[4]);
var remoteRoot = NormalizeRemote(args[5]);
var includeGeneratedReports = args.Skip(6).Any(arg => string.Equals(arg, "--include-generated-reports", StringComparison.OrdinalIgnoreCase));

if (!Directory.Exists(localRoot))
{
    Console.Error.WriteLine($"Local root not found: {localRoot}");
    return 3;
}

using var client = new SftpClient(host, port, username, password);
client.ConnectionInfo.Timeout = TimeSpan.FromSeconds(30);
client.Connect();

EnsureRemoteDirectory(client, remoteRoot);

foreach (var file in Directory.EnumerateFiles(localRoot, "*", SearchOption.AllDirectories))
{
    var relative = Path.GetRelativePath(localRoot, file).Replace('\\', '/');
    if (!includeGeneratedReports && (relative.StartsWith("data/reports/", StringComparison.OrdinalIgnoreCase) || relative.StartsWith("data/uploads/", StringComparison.OrdinalIgnoreCase)))
    {
        continue;
    }

    var remotePath = CombineRemote(remoteRoot, relative);
    EnsureRemoteDirectory(client, Path.GetDirectoryName(remotePath)?.Replace('\\', '/') ?? remoteRoot);
    await using var stream = File.OpenRead(file);
    client.UploadFile(stream, remotePath, true);
    Console.WriteLine($"Uploaded {relative}");
}

client.Disconnect();
Console.WriteLine("Deployment complete.");
return 0;

static string NormalizeRemote(string path)
{
    if (string.IsNullOrWhiteSpace(path))
    {
        return "/";
    }

    path = path.Replace('\\', '/');
    if (!path.StartsWith('/'))
    {
        path = "/" + path;
    }

    var normalized = path.TrimEnd('/');
    return normalized.Length == 0 ? "/" : normalized;
}

static string CombineRemote(string root, string relative)
{
    root = NormalizeRemote(root);
    relative = relative.Replace('\\', '/').TrimStart('/');
    return root == "/" ? "/" + relative : root + "/" + relative;
}

static void EnsureRemoteDirectory(SftpClient client, string remoteDirectory)
{
    remoteDirectory = NormalizeRemote(remoteDirectory);
    if (remoteDirectory == "/" || client.Exists(remoteDirectory))
    {
        return;
    }

    var parts = remoteDirectory.Split('/', StringSplitOptions.RemoveEmptyEntries);
    var current = "";
    foreach (var part in parts)
    {
        current += "/" + part;
        if (!client.Exists(current))
        {
            client.CreateDirectory(current);
        }
    }
}
