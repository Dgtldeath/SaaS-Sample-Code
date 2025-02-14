using System;
using System.Data;
using System.Data.SqlClient;
using System.Web.Script.Serialization;
using System.Web.UI;
using System.Collections.Generic;

public partial class InventoryOrdersAPI : Page
{
    protected void Page_Load(object sender, EventArgs e)
	{
		// Set the content type to JSON
		Response.ContentType = "application/json";
		Response.Clear();

		try
		{
			// Extract pagination parameters from the request
			int page = Convert.ToInt32(Request.QueryString["page"] ?? "1");
			int pageSize = Convert.ToInt32(Request.QueryString["pageSize"] ?? "50");
			int lastVendorOrderId = Convert.ToInt32(Request.QueryString["lastVendorOrderId"] ?? "0");

			// Fetch data from the databaseg
			DataTable dt = GetPaginatedOrders(page, pageSize, lastVendorOrderId);

			// Serialize data to JSON format
			var jsonString = DataTableToJson(dt);

			// Calculate the row count
			int rowCount = dt.Rows.Count;

			// Create a wrapper object to include the count and JSON data
			var responseWrapper = new
			{
				count = rowCount,
				InventoryOrders = jsonString
			};

			// Serialize the wrapper object
			var wrappedJsonString = new JavaScriptSerializer
			{
				MaxJsonLength = Int32.MaxValue
			}.Serialize(responseWrapper);

			// Output JSON response
			Response.Write(wrappedJsonString);
		}
		catch (Exception ex)
		{
			// Handle errors gracefully and return an error message as JSON
			var errorResponse = new
			{
				error = true,
				message = ex.Message
			};
			var json = new JavaScriptSerializer().Serialize(errorResponse);
			Response.Write(json);
		}
		finally
		{
			Response.End();
		}
	}

	private DataTable GetPaginatedOrders(int page, int pageSize, int lastVendorOrderId)
	{
		string connectionString = System.Configuration.ConfigurationManager.ConnectionStrings["privateConnectionString"].ConnectionString;

		// Calculate the offset
		int offset = (page - 1) * pageSize;

		string query = @"
			SELECT o.Order_ID, o.User_ID, o.TimePlaced, o.IsForCustomer, o.CustomerName, o.Comments, 
				   o.Store_ID, o.Pickup, o.PickupBy, o.PickupDate, o.Status_ID, os.Order_Status, 
				   i.Product_ID, i.Quantity AS ItemQuantity, p.ProductName, p.Retail, p.ModelNumber, p.Product_ID 
			FROM Production.dbo.InventoryOrders AS o
			INNER JOIN Production.dbo.InventoryOrderItem AS i ON o.Order_ID = i.Order_ID
			INNER JOIN Production.dbo.InventoryProducts AS p ON i.Product_ID = p.Product_ID
			INNER JOIN Production.dbo.InventoryOrdersStatus AS os ON o.Status_ID = os.Status_ID
			WHERE o.Order_ID > @LastVendorOrderId  
			AND o.TimePlaced >= '2024-01-01 00:00:00.000'
			ORDER BY o.Order_ID
			OFFSET @Offset ROWS FETCH NEXT @PageSize ROWS ONLY";

		using (SqlConnection connection = new SqlConnection(connectionString))
		using (SqlCommand command = new SqlCommand(query, connection))
		{
			// Add parameters for pagination
			command.Parameters.AddWithValue("@Offset", offset);
			command.Parameters.AddWithValue("@PageSize", pageSize);
			command.Parameters.AddWithValue("@LastVendorOrderId", lastVendorOrderId);

			connection.Open();
			SqlDataAdapter adapter = new SqlDataAdapter(command);
			DataTable dt = new DataTable();
			adapter.Fill(dt);
			
			// Convert TimePlaced to standard DateTime
			foreach (DataRow row in dt.Rows)
			{
				if (row["TimePlaced"] != DBNull.Value)
				{
					string dateString = row["TimePlaced"].ToString();
					long milliseconds = long.Parse(System.Text.RegularExpressions.Regex.Match(dateString, @"\d+").Value);
					row["TimePlaced"] = dateString; 
				}
			}
			
			return dt;
		}
	}



    // Convert DataTable to JSON string
    private string DataTableToJson(DataTable table)
    {
        var serializer = new JavaScriptSerializer
        {
            MaxJsonLength = Int32.MaxValue // Handle large data sets
        };

        var orders = new List<Dictionary<string, object>>();
        foreach (DataRow dr in table.Rows)
        {
            var row = new Dictionary<string, object>();
            foreach (DataColumn col in table.Columns)
            {
                row.Add(col.ColumnName, dr[col]);
            }
            orders.Add(row);
        }

        return serializer.Serialize(new { InventoryOrders = orders });
    }
}
